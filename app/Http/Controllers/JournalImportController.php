<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\EntryType;
use App\Models\JournalEntry;
use App\Models\JournalImportTemplate;
use App\Models\Office;
use App\Models\ReferenceAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class JournalImportController extends Controller
{
    private const TEMP_DISK = 'local';
    private const TEMP_DIR = 'imports/journal-temp';

    public function create(Request $request)
    {
        return view('journal.import-csv', $this->baseViewData($request));
    }

    public function preview(Request $request)
    {
        $budgetId = $this->currentBudgetId();
        $officeEnabled = Schema::hasTable('offices') && Schema::hasColumn('journal_entries', 'office_id');
        $selectedYear = $this->currentSelectedYear($request);
        $templateNotice = null;

        $validated = $request->validate([
            'csv_file' => ['nullable', 'file', 'mimes:csv,txt'],
            'temp_file' => ['nullable', 'string'],
            'delimiter' => ['nullable', Rule::in([';', ',', "\t", '|'])],
            'date_column' => ['nullable', 'string', 'max:255'],
            'description_column' => ['nullable', 'string', 'max:255'],
            'amount_column' => ['nullable', 'string', 'max:255'],
            'date_format' => ['nullable', Rule::in(['d.m.Y', 'd/m/Y', 'Y-m-d'])],
            'reference_account_id' => ['nullable', Rule::exists('reference_accounts', 'id')->where(fn ($q) => $q->where('budget_id', $budgetId))],
            'office_id' => $officeEnabled
                ? ['nullable', Rule::exists('offices', 'id')->where(fn ($q) => $q->where('budget_id', $budgetId))]
                : ['nullable'],
            'row_entry_type' => ['nullable', 'array'],
            'row_entry_type.*' => ['nullable', Rule::exists('entry_types', 'id')->where(fn ($q) => $q->where('budget_id', $budgetId))],
            'row_parent_category' => ['nullable', 'array'],
            'row_parent_category.*' => ['nullable', Rule::exists('categories', 'id')->where(fn ($q) => $q->where('budget_id', $budgetId))],
            'row_child_category' => ['nullable', 'array'],
            'row_child_category.*' => ['nullable', Rule::exists('categories', 'id')->where(fn ($q) => $q->where('budget_id', $budgetId))],
            'row_include' => ['nullable', 'array'],
            'row_include.*' => ['nullable', Rule::in(['0', '1', 0, 1])],
        ]);

        $template = null;

        $tempFile = $validated['temp_file'] ?? '';
        if ($request->hasFile('csv_file')) {
            $tempFile = $request->file('csv_file')->store(self::TEMP_DIR, self::TEMP_DISK);
        }

        if ($tempFile === '' || !Storage::disk(self::TEMP_DISK)->exists($tempFile)) {
            return redirect()->route('journal.import.csv.create')->with('error', 'Seleziona un file CSV da caricare.');
        }

        $absolutePath = Storage::disk(self::TEMP_DISK)->path($tempFile);

        $detectedDelimiter = $validated['delimiter'] ?? $template?->delimiter ?? $this->extractDelimiterFromFile($absolutePath);
        $headers = $this->extractHeaders($absolutePath, $detectedDelimiter);
        if ($headers === []) {
            return redirect()->route('journal.import.csv.create')->with('error', 'Impossibile leggere l\'intestazione del CSV.');
        }

        if (!$template) {
            $autoTemplate = $this->findBestCompatibleTemplate($budgetId, $headers, $detectedDelimiter);
            if ($autoTemplate) {
                $template = $autoTemplate;
                $templateNotice = 'Modello compatibile rilevato automaticamente: ' . $template->name . '.';
            } else {
                $templateNotice = 'Incompatibilità: nessun template compatibile con la struttura del CSV selezionato.';
            }
        }

        if (!$template) {
            return view('journal.import-csv', array_merge($this->baseViewData($request), [
                'headers' => $headers,
                'tempFile' => $tempFile,
                'mapping' => null,
                'previewRows' => [],
                'previewStats' => ['total_rows' => 0, 'valid_rows' => 0, 'invalid_rows' => 0, 'selected_rows' => 0, 'invalid_selected_rows' => 0, 'duplicate_rows' => 0],
                'selectedTemplateId' => null,
                'templateNotice' => $templateNotice,
                'templateCompatible' => false,
                'canImport' => false,
                'showPreview' => false,
            ]));
        }

        $mapping = [
            'delimiter' => $detectedDelimiter,
            'date_column' => $validated['date_column'] ?? $template?->date_column ?? $this->guessHeader($headers, ['data registrazione', 'data', 'date']),
            'description_column' => $validated['description_column'] ?? $template?->description_column ?? $this->guessHeader($headers, ['descrizione', 'description']),
            'amount_column' => $validated['amount_column'] ?? $template?->amount_column ?? $this->guessHeader($headers, ['importo', 'amount']),
            'date_format' => $validated['date_format']
                ?? $template?->date_format
                ?? $this->detectDateFormatFromFile($absolutePath, $detectedDelimiter, $validated['date_column'] ?? null),
            'reference_account_id' => (string) ($validated['reference_account_id'] ?? $template?->reference_account_id ?? ''),
            'office_id' => (string) ($validated['office_id'] ?? $template?->office_id ?? ''),
        ];

        $categoryRules = $this->categoryValidationData($budgetId);
        $preview = $this->buildPreview(
            $absolutePath,
            $mapping,
            $validated['row_entry_type'] ?? [],
            $validated['row_parent_category'] ?? [],
            $validated['row_child_category'] ?? [],
            $validated['row_include'] ?? [],
            $categoryRules,
            $selectedYear
        );

        return view('journal.import-csv', array_merge($this->baseViewData($request), [
            'headers' => $headers,
            'tempFile' => $tempFile,
            'mapping' => $mapping,
            'previewRows' => $preview['rows'],
            'previewStats' => $preview['stats'],
            'selectedTemplateId' => $template?->id,
            'templateNotice' => $templateNotice,
            'templateCompatible' => true,
            'canImport' => $preview['stats']['selected_rows'] > 0
                && $preview['stats']['invalid_selected_rows'] === 0
                && (($mapping['reference_account_id'] ?? '') !== ''),
            'showPreview' => true,
        ]));
    }

    public function store(Request $request)
    {
        $budgetId = $this->currentBudgetId();
        $officeEnabled = Schema::hasTable('offices') && Schema::hasColumn('journal_entries', 'office_id');
        $selectedYear = $this->currentSelectedYear($request);

        $validated = $request->validate([
            'temp_file' => ['required', 'string'],
            'delimiter' => ['required', Rule::in([';', ',', "\t", '|'])],
            'date_column' => ['required', 'string'],
            'description_column' => ['required', 'string'],
            'amount_column' => ['required', 'string'],
            'date_format' => ['required', Rule::in(['d.m.Y', 'd/m/Y', 'Y-m-d'])],
            'reference_account_id' => ['required', Rule::exists('reference_accounts', 'id')->where(fn ($q) => $q->where('budget_id', $budgetId))],
            'office_id' => $officeEnabled
                ? ['nullable', Rule::exists('offices', 'id')->where(fn ($q) => $q->where('budget_id', $budgetId))]
                : ['nullable'],
            'row_entry_type' => ['nullable', 'array'],
            'row_entry_type.*' => ['nullable', Rule::exists('entry_types', 'id')->where(fn ($q) => $q->where('budget_id', $budgetId))],
            'row_parent_category' => ['nullable', 'array'],
            'row_parent_category.*' => ['nullable', Rule::exists('categories', 'id')->where(fn ($q) => $q->where('budget_id', $budgetId))],
            'row_child_category' => ['nullable', 'array'],
            'row_child_category.*' => ['nullable', Rule::exists('categories', 'id')->where(fn ($q) => $q->where('budget_id', $budgetId))],
            'row_include' => ['nullable', 'array'],
            'row_include.*' => ['nullable', Rule::in(['0', '1', 0, 1])],
        ]);

        if (!Storage::disk(self::TEMP_DISK)->exists($validated['temp_file'])) {
            return redirect()->route('journal.import.csv.create')->with('error', 'File temporaneo non trovato. Ricarica il CSV.');
        }

        $absolutePath = Storage::disk(self::TEMP_DISK)->path($validated['temp_file']);

        $mapping = [
            'delimiter' => $validated['delimiter'],
            'date_column' => $validated['date_column'],
            'description_column' => $validated['description_column'],
            'amount_column' => $validated['amount_column'],
            'date_format' => $validated['date_format'],
            'reference_account_id' => (string) ($validated['reference_account_id'] ?? ''),
            'office_id' => (string) ($validated['office_id'] ?? ''),
        ];

        $categoryRules = $this->categoryValidationData($budgetId);
        $preview = $this->buildPreview(
            $absolutePath,
            $mapping,
            $validated['row_entry_type'] ?? [],
            $validated['row_parent_category'] ?? [],
            $validated['row_child_category'] ?? [],
            $validated['row_include'] ?? [],
            $categoryRules,
            $selectedYear
        );

        if ($preview['stats']['selected_rows'] === 0) {
            return back()->with('error', 'Seleziona almeno una riga da importare.')->withInput();
        }

        if ($preview['stats']['invalid_selected_rows'] > 0) {
            return back()->with('error', 'Correggi le righe evidenziate prima di importare.')->withInput();
        }

        $counter = $this->currentImportCounter();
        $imported = 0;

        DB::transaction(function () use (&$counter, &$imported, $preview, $mapping) {
            foreach ($preview['rows'] as $row) {
                if (!($row['include'] ?? false)) {
                    continue;
                }

                $counter++;

                JournalEntry::create([
                    'budget_id' => $this->currentBudgetId(),
                    'movement_number' => sprintf('IMP-%05d', $counter),
                    'entry_date' => $row['entry_date'],
                    'entry_type_id' => (int) $row['entry_type_id'],
                    'category_id' => $row['category_id'] ?: null,
                    'description' => mb_substr((string) $row['description'], 0, 1000),
                    'amount' => $row['amount'],
                    'reference_account_id' => $mapping['reference_account_id'] !== '' ? (int) $mapping['reference_account_id'] : null,
                    'office_id' => $mapping['office_id'] !== '' ? (int) $mapping['office_id'] : null,
                ]);

                $imported++;
            }
        });

        Storage::disk(self::TEMP_DISK)->delete($validated['temp_file']);

        return redirect()->route('journal.index')->with('success', "Import completato: {$imported} movimenti caricati.");
    }

    private function baseViewData(?Request $request = null): array
    {
        $budgetId = $this->currentBudgetId();
        $officeEnabled = Schema::hasTable('offices') && Schema::hasColumn('journal_entries', 'office_id');
        $availableYears = $this->availableYearsFromDb($budgetId);
        $selectedYear = $request ? $this->currentSelectedYear($request) : ((int) session('selected_year.budget.' . $budgetId, (int) now()->format('Y')));

        return [
            'availableYears' => $availableYears,
            'selectedYear' => $selectedYear,
            'yearRoute' => route('journal.import.csv.create'),
            'templates' => JournalImportTemplate::where('budget_id', $budgetId)->orderBy('name')->get(),
            'entryTypes' => EntryType::where('budget_id', $budgetId)->orderBy('name')->get(),
            'accounts' => ReferenceAccount::where('budget_id', $budgetId)->orderBy('name')->get(),
            'offices' => $officeEnabled ? Office::where('budget_id', $budgetId)->orderBy('name')->get() : collect(),
            'fieldVisibility' => $this->journalFieldVisibility($budgetId),
            'categoryRootsByType' => $this->categoryRootsByType($budgetId),
            'categoryChildrenByRoot' => $this->categoryChildrenByRoot($budgetId),
            'categoryIndex' => $this->categoryIndex($budgetId),
            'headers' => [],
            'tempFile' => '',
            'mapping' => null,
            'previewRows' => [],
            'previewStats' => ['total_rows' => 0, 'valid_rows' => 0, 'invalid_rows' => 0],
            'selectedTemplateId' => null,
            'templateNotice' => null,
            'templateCompatible' => false,
            'canImport' => false,
            'showPreview' => false,
        ];
    }

    private function isTemplateCompatible(JournalImportTemplate $template, array $headers): bool
    {
        $normalizedHeaders = collect($headers)
            ->map(fn ($h) => mb_strtolower(trim((string) $h)))
            ->filter(fn ($h) => $h !== '')
            ->values()
            ->all();

        $hasHeader = function (?string $value) use ($normalizedHeaders): bool {
            $needle = mb_strtolower(trim((string) $value));
            return $needle !== '' && in_array($needle, $normalizedHeaders, true);
        };

        return $hasHeader($template->date_column)
            && $hasHeader($template->description_column)
            && $hasHeader($template->amount_column);
    }

    private function findBestCompatibleTemplate(int $budgetId, array $headers, string $detectedDelimiter): ?JournalImportTemplate
    {
        $templates = JournalImportTemplate::query()
            ->where('budget_id', $budgetId)
            ->orderBy('updated_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $best = null;
        $bestScore = -1;

        foreach ($templates as $template) {
            if (!$this->isTemplateCompatible($template, $headers)) {
                continue;
            }

            $score = 1;
            if ((string) $template->delimiter === (string) $detectedDelimiter) {
                $score += 2;
            }

            if ($score > $bestScore) {
                $best = $template;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private function categoryIndex(int $budgetId): array
    {
        $categories = Category::where('budget_id', $budgetId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'entry_type_id']);

        $byId = [];
        foreach ($categories as $category) {
            $byId[(int) $category->id] = [
                'id' => (int) $category->id,
                'name' => $category->name,
                'parent_id' => $category->parent_id ? (int) $category->parent_id : null,
                'entry_type_id' => (int) $category->entry_type_id,
            ];
        }

        return $byId;
    }

    private function categoryRootsByType(int $budgetId): array
    {
        $roots = Category::where('budget_id', $budgetId)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'entry_type_id']);

        $grouped = [];
        foreach ($roots as $root) {
            $grouped[(int) $root->entry_type_id][] = [
                'id' => (int) $root->id,
                'name' => $root->name,
            ];
        }

        return $grouped;
    }

    private function categoryChildrenByRoot(int $budgetId): array
    {
        $children = Category::where('budget_id', $budgetId)
            ->whereNotNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        $grouped = [];
        foreach ($children as $child) {
            $grouped[(int) $child->parent_id][] = [
                'id' => (int) $child->id,
                'name' => $child->name,
            ];
        }

        return $grouped;
    }

    private function categoryValidationData(int $budgetId): array
    {
        $categories = Category::where('budget_id', $budgetId)
            ->get(['id', 'parent_id', 'entry_type_id']);

        $rootByType = [];
        $rootMeta = [];
        $childMeta = [];

        foreach ($categories as $category) {
            $id = (int) $category->id;
            $typeId = (int) $category->entry_type_id;

            if ($category->parent_id === null) {
                $rootByType[$typeId][] = $id;
                $rootMeta[$id] = ['entry_type_id' => $typeId];
            } else {
                $childMeta[$id] = [
                    'parent_id' => (int) $category->parent_id,
                    'entry_type_id' => $typeId,
                ];
            }
        }

        $entryTypeIds = EntryType::where('budget_id', $budgetId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return [
            'entry_type_ids' => $entryTypeIds,
            'root_by_type' => $rootByType,
            'root_meta' => $rootMeta,
            'child_meta' => $childMeta,
        ];
    }

    private function extractHeaders(string $path, ?string $preferredDelimiter): array
    {
        $rawLines = file($path, FILE_IGNORE_NEW_LINES);
        if ($rawLines === false || count($rawLines) === 0) {
            return [];
        }

        $firstLine = $this->toUtf8((string) $rawLines[0]);
        $delimiter = $preferredDelimiter ?? $this->detectDelimiter($firstLine);

        $headers = array_map(fn ($v) => trim((string) $v), str_getcsv($firstLine, $delimiter));

        while (!empty($headers) && end($headers) === '') {
            array_pop($headers);
        }

        return $headers;
    }

    private function extractDelimiterFromFile(string $path): string
    {
        $rawLines = file($path, FILE_IGNORE_NEW_LINES);
        if ($rawLines === false || count($rawLines) === 0) {
            return ';';
        }

        return $this->detectDelimiter($this->toUtf8((string) $rawLines[0]));
    }

    private function detectDateFormatFromFile(string $path, string $delimiter, ?string $preferredColumn): string
    {
        $rows = $this->parseCsvRows($path, $delimiter);
        if ($rows === []) {
            return 'd/m/Y';
        }

        $firstRow = reset($rows);
        if (!is_array($firstRow) || $firstRow === []) {
            return 'd/m/Y';
        }

        $candidateColumns = [];
        if (is_string($preferredColumn) && $preferredColumn !== '') {
            $candidateColumns[] = $preferredColumn;
        }

        foreach (array_keys($firstRow) as $column) {
            $lower = mb_strtolower((string) $column);
            if (str_contains($lower, 'data') || str_contains($lower, 'date')) {
                $candidateColumns[] = (string) $column;
            }
        }

        $candidateColumns = array_values(array_unique($candidateColumns));
        if ($candidateColumns === []) {
            $candidateColumns = [(string) array_key_first($firstRow)];
        }

        foreach ($candidateColumns as $column) {
            foreach ($rows as $row) {
                $raw = trim((string) ($row[$column] ?? ''));
                if ($raw === '') {
                    continue;
                }
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $raw)) {
                    return 'd/m/Y';
                }
                if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $raw)) {
                    return 'd.m.Y';
                }
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
                    return 'Y-m-d';
                }
            }
        }

        return 'd/m/Y';
    }

    private function buildPreview(
        string $path,
        array $mapping,
        array $rowTypeChoices,
        array $rowParentChoices,
        array $rowChildChoices,
        array $rowIncludeChoices,
        array $categoryRules,
        int $selectedYear
    ): array {
        $rows = $this->parseCsvRows($path, $mapping['delimiter']);

        $previewRows = [];
        $existingDuplicateIndex = $this->existingDuplicateIndex();
        $seenExactInCsv = [];

        foreach ($rows as $line => $row) {
            $errors = [];

            $dateValue = $row[$mapping['date_column']] ?? '';
            $description = trim((string) ($row[$mapping['description_column']] ?? ''));
            $amountValue = $row[$mapping['amount_column']] ?? '';
            $voiceLabel = '';

            $entryTypeId = (int) ($rowTypeChoices[$line] ?? 0);
            $parentId = (int) ($rowParentChoices[$line] ?? 0);
            $childId = (int) ($rowChildChoices[$line] ?? 0);
            $lineKey = (string) $line;

            [$entryDate, $dateError, $dateNotice] = $this->parseDateByFormat($dateValue, $mapping['date_format']);
            if ($entryDate === null) {
                $errors[] = $dateError ?? 'Data non valida';
            } elseif ((int) substr($entryDate, 0, 4) !== $selectedYear) {
                $errors[] = 'Data fuori anno selezionato (' . $selectedYear . ')';
            }

            $amount = $this->parseAmount($amountValue);
            if ($amount === null) {
                $errors[] = 'Importo non valido';
            }

            if ($description === '') {
                $errors[] = 'Descrizione vuota';
            }

            if (!in_array($entryTypeId, $categoryRules['entry_type_ids'], true)) {
                $errors[] = 'Tipo movimento non selezionato';
            }

            if ($parentId <= 0) {
                $errors[] = 'Categoria non selezionata';
            } elseif (!isset($categoryRules['root_meta'][$parentId])) {
                $errors[] = 'Categoria non valida';
            } elseif (($categoryRules['root_meta'][$parentId]['entry_type_id'] ?? 0) !== $entryTypeId) {
                $errors[] = 'Categoria non coerente col tipo';
            }

            if ($childId > 0) {
                $childMeta = $categoryRules['child_meta'][$childId] ?? null;
                if ($childMeta === null) {
                    $errors[] = 'Subcategoria non valida';
                } elseif ((int) $childMeta['parent_id'] !== $parentId) {
                    $errors[] = 'Subcategoria non coerente con la categoria';
                } elseif ((int) $childMeta['entry_type_id'] !== $entryTypeId) {
                    $errors[] = 'Subcategoria non coerente col tipo';
                }
            }

            $categoryId = $childId > 0 ? $childId : ($parentId > 0 ? $parentId : 0);
            $potentialDuplicate = false;
            $duplicateReason = '';
            $isExactDuplicate = false;

            if ($entryDate !== null && $amount !== null && $description !== '') {
                $baseKey = $this->duplicateBaseKey($entryDate, $amount);
                $normalizedDescription = $this->normalizeDescription($description);
                $exactKey = $this->duplicateExactKey($entryDate, $amount, $normalizedDescription);

                $hasExactInDb = isset($existingDuplicateIndex['exact'][$exactKey]);
                $hasExactInCsv = isset($seenExactInCsv[$exactKey]);
                $hasSameBaseInDb = isset($existingDuplicateIndex['by_base'][$baseKey]);
                $hasDifferentDescriptionInDb = $hasSameBaseInDb
                    && !isset($existingDuplicateIndex['by_base'][$baseKey][$normalizedDescription]);

                if ($hasExactInDb || $hasExactInCsv) {
                    $potentialDuplicate = true;
                    $duplicateReason = 'Duplicato';
                    $isExactDuplicate = true;
                } elseif ($hasDifferentDescriptionInDb) {
                    $potentialDuplicate = true;
                    $duplicateReason = 'Probabile duplicato';
                }

                $seenExactInCsv[$exactKey] = true;
            }

            $hasExplicitFlag = array_key_exists($lineKey, $rowIncludeChoices) || array_key_exists($line, $rowIncludeChoices);
            $rawInclude = $rowIncludeChoices[$lineKey] ?? $rowIncludeChoices[$line] ?? null;
            $include = $hasExplicitFlag ? ((string) $rawInclude === '1') : !$isExactDuplicate;

            $previewRows[] = [
                'line' => $line,
                'voice' => $voiceLabel,
                'date_raw' => $dateValue,
                'entry_date' => $entryDate,
                'description' => $description,
                'amount' => $amount,
                'entry_type_id' => $entryTypeId > 0 ? $entryTypeId : null,
                'parent_category_id' => $parentId > 0 ? $parentId : null,
                'child_category_id' => $childId > 0 ? $childId : null,
                'category_id' => $categoryId > 0 ? $categoryId : null,
                'date_notice' => $dateNotice,
                'potential_duplicate' => $potentialDuplicate,
                'duplicate_reason' => $duplicateReason,
                'include' => $include,
                'errors' => $errors,
            ];
        }

        $invalidRows = collect($previewRows)->filter(fn ($r) => $r['errors'] !== [])->count();
        $selectedRows = collect($previewRows)->filter(fn ($r) => (bool) ($r['include'] ?? false))->count();
        $invalidSelectedRows = collect($previewRows)->filter(fn ($r) => (bool) ($r['include'] ?? false) && $r['errors'] !== [])->count();
        $duplicateRows = collect($previewRows)->filter(fn ($r) => (bool) ($r['potential_duplicate'] ?? false))->count();

        return [
            'rows' => $previewRows,
            'stats' => [
                'total_rows' => count($previewRows),
                'valid_rows' => count($previewRows) - $invalidRows,
                'invalid_rows' => $invalidRows,
                'selected_rows' => $selectedRows,
                'invalid_selected_rows' => $invalidSelectedRows,
                'duplicate_rows' => $duplicateRows,
            ],
        ];
    }

    private function parseCsvRows(string $path, string $delimiter): array
    {
        $rawLines = file($path, FILE_IGNORE_NEW_LINES);
        if ($rawLines === false || count($rawLines) <= 1) {
            return [];
        }

        $headerLine = $this->toUtf8((string) array_shift($rawLines));
        $headers = array_map(fn ($v) => trim((string) $v), str_getcsv($headerLine, $delimiter));

        while (!empty($headers) && end($headers) === '') {
            array_pop($headers);
        }

        $rows = [];
        $lineNumber = 1;

        foreach ($rawLines as $rawLine) {
            $lineNumber++;
            $utfLine = $this->toUtf8((string) $rawLine);
            if (trim($utfLine) === '') {
                continue;
            }

            $values = str_getcsv($utfLine, $delimiter);
            while (!empty($values) && trim((string) end($values)) === '') {
                array_pop($values);
            }

            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = trim((string) ($values[$index] ?? ''));
            }

            $rows[$lineNumber] = $row;
        }

        return $rows;
    }

    private function guessHeader(array $headers, array $needles): string
    {
        foreach ($headers as $header) {
            $h = mb_strtolower(trim($header));
            foreach ($needles as $needle) {
                if (str_contains($h, $needle)) {
                    return $header;
                }
            }
        }

        return $headers[0] ?? '';
    }

    private function detectDelimiter(string $line): string
    {
        $candidates = [';', ',', "\t", '|'];
        $bestDelimiter = ';';
        $bestColumns = 0;

        foreach ($candidates as $candidate) {
            $count = count(str_getcsv($line, $candidate));
            if ($count > $bestColumns) {
                $bestColumns = $count;
                $bestDelimiter = $candidate;
            }
        }

        return $bestDelimiter;
    }

    private function parseDateByFormat(string $value, string $format): array
    {
        $clean = trim($value);
        if ($clean === '') {
            return [null, 'Data vuota', null];
        }

        $patternByFormat = [
            'd/m/Y' => '/^\d{2}\/\d{2}\/\d{4}$/',
            'd.m.Y' => '/^\d{2}\.\d{2}\.\d{4}$/',
            'Y-m-d' => '/^\d{4}\-\d{2}\-\d{2}$/',
        ];
        $pattern = $patternByFormat[$format] ?? null;
        if ($pattern && !preg_match($pattern, $clean)) {
            foreach (['d/m/Y', 'd.m.Y', 'Y-m-d'] as $candidate) {
                if ($candidate === $format) {
                    continue;
                }
                [$iso, $ok] = $this->tryParseDateStrict($clean, $candidate);
                if ($ok && $iso !== null) {
                    $reconverted = $this->formatDateForPattern($iso, $format);
                    return [
                        $iso,
                        null,
                        'Data non compatibile, sarà riconvertita in ' . ($reconverted ?? $iso),
                    ];
                }
            }

            return [null, 'Data non valida', null];
        }

        [$iso, $ok] = $this->tryParseDateStrict($clean, $format);
        if (!$ok || $iso === null) {
            return [null, 'Data non valida', null];
        }

        return [$iso, null, null];
    }

    private function tryParseDateStrict(string $value, string $format): array
    {
        $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
        $errors = \DateTimeImmutable::getLastErrors();
        $hasErrors = is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);

        if (!($date instanceof \DateTimeImmutable) || $hasErrors || $date->format($format) !== $value) {
            return [null, false];
        }

        $year = (int) $date->format('Y');
        if ($year < 2000 || $year > 2100) {
            return [null, false];
        }

        return [$date->format('Y-m-d'), true];
    }

    private function formatDateForPattern(string $isoDate, string $format): ?string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $isoDate);
        if (!($date instanceof \DateTimeImmutable)) {
            return null;
        }

        return $date->format($format);
    }

    private function parseAmount(string $raw): ?float
    {
        $clean = preg_replace('/[€\s\x{00A0}]/u', '', $raw);
        $clean = str_replace('.', '', $clean);
        $clean = str_replace(',', '.', $clean);

        if ($clean === '' || $clean === '-' || !is_numeric($clean)) {
            return null;
        }

        $amount = (float) $clean;

        return abs($amount) >= 0.01 ? abs($amount) : null;
    }

    private function toUtf8(string $line): string
    {
        if (str_starts_with($line, "\xEF\xBB\xBF")) {
            $line = substr($line, 3);
        }

        return mb_convert_encoding($line, 'UTF-8', 'UTF-8, Windows-1252, ISO-8859-1');
    }

    private function currentImportCounter(): int
    {
        $max = 0;

        $numbers = JournalEntry::query()
            ->where('budget_id', $this->currentBudgetId())
            ->where('movement_number', 'like', 'IMP-%')
            ->pluck('movement_number');

        foreach ($numbers as $number) {
            if (preg_match('/^IMP-(\d+)$/', (string) $number, $match)) {
                $max = max($max, (int) $match[1]);
            }
        }

        return $max;
    }

    private function resolveTemplateEntryTypeId(array $previewRows): int
    {
        foreach ($previewRows as $row) {
            $id = (int) ($row['entry_type_id'] ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return (int) EntryType::where('budget_id', $this->currentBudgetId())
            ->orderBy('id')
            ->value('id');
    }

    private function existingDuplicateIndex(): array
    {
        $exact = [];
        $byBase = [];

        JournalEntry::query()
            ->where('budget_id', $this->currentBudgetId())
            ->select(['entry_date', 'amount', 'description'])
            ->orderBy('id')
            ->chunk(1000, function ($entries) use (&$exact, &$byBase) {
                foreach ($entries as $entry) {
                    $entryDate = $entry->entry_date instanceof \DateTimeInterface
                        ? $entry->entry_date->format('Y-m-d')
                        : (string) $entry->entry_date;

                    $description = (string) ($entry->description ?? '');
                    if ($entryDate === '' || $description === '') {
                        continue;
                    }

                    $amount = abs((float) $entry->amount);
                    if ($amount < 0.01) {
                        continue;
                    }

                    $normalizedDescription = $this->normalizeDescription($description);
                    $exactKey = $this->duplicateExactKey($entryDate, $amount, $normalizedDescription);
                    $baseKey = $this->duplicateBaseKey($entryDate, $amount);

                    $exact[$exactKey] = true;
                    $byBase[$baseKey][$normalizedDescription] = true;
                }
            });

        return [
            'exact' => $exact,
            'by_base' => $byBase,
        ];
    }

    private function duplicateExactKey(string $entryDate, float $amount, string $normalizedDescription): string
    {
        return sha1($this->duplicateBaseKey($entryDate, $amount) . '|' . $normalizedDescription);
    }

    private function duplicateBaseKey(string $entryDate, float $amount): string
    {
        return sha1($entryDate . '|' . number_format(abs($amount), 2, '.', ''));
    }

    private function normalizeDescription(string $description): string
    {
        $normalized = str_replace("\xc2\xa0", ' ', $description);
        $normalized = preg_replace('/\s+/u', ' ', $normalized);
        return mb_strtolower(trim((string) $normalized));
    }

    private function currentSelectedYear(Request $request): int
    {
        $sessionKey = 'selected_year.budget.' . $this->currentBudgetId();
        $requestedYear = (int) $request->input('year', $request->query('year', 0));
        if ($requestedYear > 0) {
            $request->session()->put($sessionKey, $requestedYear);
            return $requestedYear;
        }

        $storedYear = (int) $request->session()->get($sessionKey, 0);
        if ($storedYear > 0) {
            return $storedYear;
        }

        $fallbackYear = (int) now()->format('Y');
        $request->session()->put($sessionKey, $fallbackYear);

        return $fallbackYear;
    }

}
