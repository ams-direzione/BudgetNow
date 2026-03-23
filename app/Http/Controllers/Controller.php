<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\BudgetOption;
use App\Models\BudgetForecast;
use App\Models\JournalEntry;
use App\Services\CurrentBudget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

abstract class Controller
{
    protected function currentBudget(): Budget
    {
        return app(CurrentBudget::class)->current();
    }

    protected function currentBudgetId(): int
    {
        return $this->currentBudget()->id;
    }

    protected function ensureInCurrentBudget(Model $model): void
    {
        if ((int) ($model->budget_id ?? 0) !== $this->currentBudgetId()) {
            abort(404);
        }
    }

    protected function availableYearsFromDb(int $budgetId, bool $includeForecastYears = false): array
    {
        $yearsFromJournal = JournalEntry::query()
            ->where('budget_id', $budgetId)
            ->selectRaw('YEAR(entry_date) as year')
            ->distinct()
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->all();

        $years = $yearsFromJournal;

        if ($includeForecastYears) {
            $yearsFromForecast = BudgetForecast::query()
                ->where('budget_id', $budgetId)
                ->distinct()
                ->pluck('year')
                ->map(fn ($year) => (int) $year)
                ->all();

            $years = array_merge($years, $yearsFromForecast);
        }

        // L'anno corrente deve essere sempre disponibile nella selezione.
        $years[] = (int) now()->format('Y');

        $years = collect($years)
            ->filter(fn ($year) => $year > 0)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return $years;
    }

    protected function resolveSelectedYear(Request $request, array $availableYears): int
    {
        $sessionKey = 'selected_year.budget.' . $this->currentBudgetId();
        $requestedYear = (int) $request->query('year', 0);

        if ($requestedYear > 0 && in_array($requestedYear, $availableYears, true)) {
            $selectedYear = $requestedYear;
        } else {
            $storedYear = (int) $request->session()->get($sessionKey, 0);
            $selectedYear = in_array($storedYear, $availableYears, true)
                ? $storedYear
                : $availableYears[0];
        }

        $request->session()->put($sessionKey, $selectedYear);

        return $selectedYear;
    }

    protected function journalFieldVisibility(int $budgetId): array
    {
        if (! Schema::hasTable('budget_options')) {
            return [
                'show_account' => true,
                'show_office' => false,
            ];
        }

        $options = BudgetOption::firstOrCreate(
            ['budget_id' => $budgetId],
            ['show_account' => true, 'show_office' => false]
        );

        return [
            'show_account' => (bool) $options->show_account,
            'show_office' => (bool) $options->show_office,
        ];
    }
}
