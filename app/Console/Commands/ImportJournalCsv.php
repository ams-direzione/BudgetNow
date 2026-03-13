<?php

namespace App\Console\Commands;

use App\Models\Budget;
use App\Models\Category;
use App\Models\EntryType;
use App\Models\JournalEntry;
use App\Models\ReferenceAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportJournalCsv extends Command
{
    protected $signature   = 'import:journal {file? : Percorso del file CSV (default: import/)} {--budget_id= : ID budget di destinazione}';
    protected $description = 'Importa movimenti dal file CSV nel Libro Giornale';

    // Cache runtime
    private array $entryTypes        = [];
    private array $categories        = [];  // "ENTRYTYPE|PARENT|CHILD" => id  or  "ENTRYTYPE|NAME" => id
    private array $referenceAccounts = [];  // "name" => id
    private int   $counter           = 0;
    private int   $skipped           = 0;
    private int   $imported          = 0;
    private int   $budgetId;

    public function handle(): int
    {
        $file = $this->argument('file')
            ?? base_path('import/20260302-Budget - GD - v.20250521.CSV');

        if (! file_exists($file)) {
            $this->error("File non trovato: {$file}");
            return self::FAILURE;
        }

        $this->info("Inizio importazione: {$file}");

        try {
            $this->budgetId = $this->resolveBudgetId();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $lastMovement = JournalEntry::where('budget_id', $this->budgetId)
            ->where('movement_number', 'like', 'IMP-%')
            ->orderByDesc('id')
            ->value('movement_number');

        if ($lastMovement && preg_match('/^IMP-(\d+)$/', $lastMovement, $m)) {
            $this->counter = (int) $m[1];
        }
        $this->loadEntryTypes();

        DB::transaction(function () use ($file) {
            $handle = fopen($file, 'rb');

            // Skip BOM/header
            $firstLine = fgets($handle);

            while (($rawLine = fgets($handle)) !== false) {
                $line = iconv('CP1252', 'UTF-8//TRANSLIT', $rawLine);
                $line = rtrim($line, "\r\n");

                if (trim($line) === '') {
                    continue;
                }

                $cols = explode(';', $line);

                // Expect at least 5 columns: Data;Tipo;Voce;Descrizione;Importo[;Conto]
                if (count($cols) < 5) {
                    $this->skipped++;
                    continue;
                }

                [$dateRaw, $tipoRaw, $voceRaw, $descRaw, $importoRaw] = $cols;
                $contoRaw = trim($cols[5] ?? '');

                // Parse date (DD/MM/YYYY → Y-m-d)
                $dateRaw = trim($dateRaw);
                $dateParts = explode('/', $dateRaw);
                if (count($dateParts) !== 3) {
                    $this->skipped++;
                    continue;
                }
                $entryDate = sprintf('%s-%s-%s', $dateParts[2], $dateParts[1], $dateParts[0]);

                // Parse amount
                $amount = $this->parseAmount($importoRaw);
                if ($amount === null) {
                    $this->warn("  Skip (importo non valido): {$line}");
                    $this->skipped++;
                    continue;
                }

                // Entry type
                $tipo = trim($tipoRaw);
                $entryTypeId = $this->resolveEntryType($tipo);
                if ($entryTypeId === null) {
                    $this->warn("  Skip (tipo sconosciuto «{$tipo}»): {$line}");
                    $this->skipped++;
                    continue;
                }

                // Category
                $voce        = trim($voceRaw);
                $categoryId  = $this->resolveCategory($voce, $entryTypeId);

                // Reference account
                $conto             = trim($contoRaw);
                $refAccountId      = $this->resolveReferenceAccount($conto);

                // Movement number
                $this->counter++;
                $movementNumber = sprintf('IMP-%05d', $this->counter);

                JournalEntry::create([
                    'budget_id'            => $this->budgetId,
                    'movement_number'      => $movementNumber,
                    'entry_date'           => $entryDate,
                    'entry_type_id'        => $entryTypeId,
                    'category_id'          => $categoryId,
                    'description'          => mb_substr(trim($descRaw), 0, 1000) ?: null,
                    'amount'               => $amount,
                    'reference_account_id' => $refAccountId,
                ]);

                $this->imported++;
            }

            fclose($handle);
        });

        $this->info("Completato: {$this->imported} movimenti importati, {$this->skipped} righe saltate.");
        return self::SUCCESS;
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function loadEntryTypes(): void
    {
        if (! EntryType::where('budget_id', $this->budgetId)->exists()) {
            EntryType::create(['budget_id' => $this->budgetId, 'name' => 'Entrata']);
            EntryType::create(['budget_id' => $this->budgetId, 'name' => 'Uscita']);
        }

        foreach (EntryType::where('budget_id', $this->budgetId)->get() as $et) {
            $this->entryTypes[strtolower($et->name)] = $et->id;
        }
    }

    private function resolveEntryType(string $tipo): ?int
    {
        $map = [
            'ricavi' => 'entrata',
            'costi'  => 'uscita',
        ];

        $normalized = $map[strtolower($tipo)] ?? strtolower($tipo);
        return $this->entryTypes[$normalized] ?? null;
    }

    /**
     * Resolve (or create) a category from a Voce string.
     * If Voce contains " - ", split into parent and subcategory.
     */
    private function resolveCategory(string $voce, int $entryTypeId): ?int
    {
        if ($voce === '' || $voce === '-') {
            return null;
        }

        // Check if it's a PARENT - CHILD pattern
        // Use " - " (with spaces) as separator to avoid false positives
        if (str_contains($voce, ' - ')) {
            $parts      = explode(' - ', $voce, 2);
            $parentName = trim($parts[0]);
            $childName  = trim($parts[1]);

            $parentId  = $this->findOrCreateCategory($parentName, null, $entryTypeId);
            $cacheKey  = "{$entryTypeId}|{$parentName}|{$childName}";

            if (! isset($this->categories[$cacheKey])) {
                $child = Category::firstOrCreate(
                    [
                        'budget_id' => $this->budgetId,
                        'name' => $childName,
                        'parent_id' => $parentId,
                        'entry_type_id' => $entryTypeId,
                    ],
                    []
                );
                $this->categories[$cacheKey] = $child->id;
            }

            return $this->categories[$cacheKey];
        }

        // Simple category (no dash)
        return $this->findOrCreateCategory($voce, null, $entryTypeId);
    }

    private function findOrCreateCategory(string $name, ?int $parentId, int $entryTypeId): int
    {
        $cacheKey = $parentId ? "{$entryTypeId}|{$parentId}|{$name}" : "{$entryTypeId}|root|{$name}";

        if (! isset($this->categories[$cacheKey])) {
            $category = Category::firstOrCreate(
                [
                    'budget_id' => $this->budgetId,
                    'name' => $name,
                    'parent_id' => $parentId,
                    'entry_type_id' => $entryTypeId,
                ],
                []
            );
            $this->categories[$cacheKey] = $category->id;
        }

        return $this->categories[$cacheKey];
    }

    private function resolveReferenceAccount(string $conto): int
    {
        $name = $conto !== '' ? $conto : 'Principale';

        if (! isset($this->referenceAccounts[$name])) {
            $account = ReferenceAccount::firstOrCreate(
                ['budget_id' => $this->budgetId, 'name' => $name],
                ['account_code' => $this->buildAccountCode($name)]
            );
            $this->referenceAccounts[$name] = $account->id;
        }

        return $this->referenceAccounts[$name];
    }

    private function buildAccountCode(string $name): string
    {
        // Strip non-alphanumeric, uppercase, max 20 chars
        $code = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $name));
        $code = substr($code ?: 'CONTO', 0, 20);

        // Ensure uniqueness by appending a counter if needed
        $original = $code;
        $i = 1;
        while (ReferenceAccount::where('budget_id', $this->budgetId)->where('account_code', $code)->exists()) {
            $code = $original . $i++;
        }

        return $code;
    }

    /**
     * Parse Italian decimal format: "1.234,56 €" → 1234.56
     * Returns null if the amount is invalid or a placeholder (" -   ").
     */
    private function parseAmount(string $raw): ?float
    {
        // Remove €, currency artifacts, non-breaking spaces
        $clean = preg_replace('/[€\x{20AC}\x{00A0}\s]/u', '', $raw);
        // Remove thousands separator (dot before digits)
        $clean = str_replace('.', '', $clean);
        // Replace decimal comma with dot
        $clean = str_replace(',', '.', $clean);

        // Must be a valid non-zero number after cleaning
        if ($clean === '' || $clean === '-' || ! is_numeric($clean)) {
            return null;
        }

        $value = (float) $clean;

        // Use absolute value — direction is encoded in entry_type_id
        return abs($value) >= 0.01 ? abs($value) : null;
    }

    private function resolveBudgetId(): int
    {
        $option = $this->option('budget_id');
        if ($option) {
            $budget = Budget::find($option);
            if (! $budget) {
                throw new \RuntimeException("Budget non trovato: {$option}");
            }

            return $budget->id;
        }

        return Budget::query()->orderBy('id')->value('id')
            ?? Budget::create(['user_id' => null, 'name' => 'Budget Principale'])->id;
    }
}
