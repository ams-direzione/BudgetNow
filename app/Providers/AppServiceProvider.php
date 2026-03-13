<?php

namespace App\Providers;

use App\Models\EntryType;
use App\Services\CurrentBudget;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Dati globali del layout, filtrati per budget attivo.
        View::composer('layouts.app', function ($view) {
            try {
                $currentBudget = app(CurrentBudget::class)->current();
                $view->with('activeBudget', $currentBudget);
                $view->with('availableBudgets', app(CurrentBudget::class)->all());
                $view->with(
                    'navEntryTypes',
                    EntryType::where('budget_id', $currentBudget->id)->orderBy('name')->get()
                );
            } catch (\Throwable) {
                // Evita eccezioni durante bootstrap/comandi quando il DB non è pronto.
                $view->with('activeBudget', null);
                $view->with('availableBudgets', collect());
                $view->with('navEntryTypes', collect());
            }
        });
    }
}
