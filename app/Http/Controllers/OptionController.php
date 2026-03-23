<?php

namespace App\Http\Controllers;

use App\Models\BudgetOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class OptionController extends Controller
{
    public function edit()
    {
        if (! Schema::hasTable('budget_options')) {
            $settings = (object) ['show_account' => true, 'show_office' => false];
            return view('options.edit', compact('settings'))
                ->with('error', 'Tabella opzioni non presente. Esegui le migrazioni per salvare le impostazioni.');
        }

        $settings = BudgetOption::firstOrCreate(
            ['budget_id' => $this->currentBudgetId()],
            ['show_account' => true, 'show_office' => false]
        );

        return view('options.edit', compact('settings'));
    }

    public function update(Request $request)
    {
        if (! Schema::hasTable('budget_options')) {
            return redirect()->route('opzioni.edit')
                ->with('error', 'Tabella opzioni non presente. Esegui le migrazioni per salvare le impostazioni.');
        }

        $data = $request->validate([
            'show_account' => ['required', 'boolean'],
            'show_office' => ['required', 'boolean'],
        ]);

        BudgetOption::updateOrCreate(
            ['budget_id' => $this->currentBudgetId()],
            [
                'show_account' => (bool) $data['show_account'],
                'show_office' => (bool) $data['show_office'],
            ]
        );

        return redirect()->route('opzioni.edit')->with('success', 'Opzioni aggiornate con successo.');
    }
}
