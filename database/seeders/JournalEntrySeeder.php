<?php

namespace Database\Seeders;

use App\Models\ReferenceAccount;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JournalEntrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accountIds = ReferenceAccount::query()->pluck('id')->all();

        $incomeCategories = [
            'Vendite' => ['VEN-001', 'VEN-002', 'VEN-003'],
            'Rimborsi' => ['RIM-010', 'RIM-011'],
            'Interessi Attivi' => ['INT-020'],
            'Contributi' => ['CON-030', 'CON-031'],
        ];

        $expenseCategories = [
            'Affitti' => ['AFF-100'],
            'Fornitori' => ['FOR-110', 'FOR-111', 'FOR-112'],
            'Utenze' => ['UTE-120', 'UTE-121'],
            'Stipendi' => ['STI-130', 'STI-131'],
            'Marketing' => ['MKT-140', 'MKT-141'],
            'Manutenzioni' => ['MAN-150', 'MAN-151'],
        ];

        $entries = [];
        $movementCounter = 1;
        $now = now();

        foreach ([2025, 2026] as $year) {
            for ($month = 1; $month <= 12; $month++) {
                for ($i = 0; $i < 10; $i++) {
                    $type = fake()->boolean(35) ? 'entrata' : 'uscita';
                    $categories = $type === 'entrata' ? $incomeCategories : $expenseCategories;
                    $category = fake()->randomElement(array_keys($categories));
                    $itemCode = fake()->randomElement($categories[$category]);

                    $amount = $type === 'entrata'
                        ? fake()->randomFloat(2, 300, 6500)
                        : fake()->randomFloat(2, 80, 4200);

                    $entries[] = [
                        'movement_number' => sprintf('MV-%d-%04d', $year, $movementCounter++),
                        'entry_date' => Carbon::create($year, $month, random_int(1, 28))->format('Y-m-d'),
                        'type' => $type,
                        'category' => $category,
                        'item_code' => $itemCode,
                        'description' => sprintf('%s - %s mese %02d/%d', ucfirst($type), $category, $month, $year),
                        'amount' => $amount,
                        'reference_account_id' => fake()->randomElement($accountIds),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        foreach (array_chunk($entries, 300) as $chunk) {
            DB::table('journal_entries')->insert($chunk);
        }
    }
}
