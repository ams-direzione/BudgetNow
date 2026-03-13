<?php

namespace Database\Seeders;

use App\Models\ReferenceAccount;
use Illuminate\Database\Seeder;

class ReferenceAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accounts = [
            ['name' => 'Conto Corrente Principale', 'account_code' => 'CC-001', 'bank_name' => 'Banca Centrale'],
            ['name' => 'Conto Risparmi', 'account_code' => 'RS-010', 'bank_name' => 'Banca Centrale'],
            ['name' => 'Carta Aziendale', 'account_code' => 'CA-202', 'bank_name' => 'Credito Europeo'],
            ['name' => 'Cassa Contanti', 'account_code' => 'CS-099', 'bank_name' => 'Gestione Interna'],
            ['name' => 'Conto Investimenti', 'account_code' => 'IV-404', 'bank_name' => 'Mercato Invest'],
        ];

        ReferenceAccount::insert($accounts);
    }
}
