<?php

namespace App\Http\Controllers;

use App\Models\EntryType;
use App\Models\JournalEntry;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        $budgetId = $this->currentBudgetId();

        $availableYears = JournalEntry::query()
            ->where('budget_id', $budgetId)
            ->selectRaw('YEAR(entry_date) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->all();

        if ($availableYears === []) {
            $availableYears = [(int) now()->format('Y')];
        }

        $selectedYear = (int) $request->query('year', $availableYears[0]);
        if (! in_array($selectedYear, $availableYears, true)) {
            $selectedYear = $availableYears[0];
        }

        $entrataId = EntryType::where('name', 'Entrata')->value('id');
        $uscitaId  = EntryType::where('name', 'Uscita')->value('id');

        $baseQuery = JournalEntry::query()
            ->where('budget_id', $budgetId)
            ->whereYear('entry_date', $selectedYear);

        $incomeTotal    = (clone $baseQuery)->where('entry_type_id', $entrataId)->sum('amount');
        $expenseTotal   = (clone $baseQuery)->where('entry_type_id', $uscitaId)->sum('amount');
        $movementsCount = (clone $baseQuery)->count();

        $recentEntries = JournalEntry::query()
            ->with(['referenceAccount', 'entryType', 'category'])
            ->where('budget_id', $budgetId)
            ->whereYear('entry_date', $selectedYear)
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return view('home.index', [
            'availableYears' => $availableYears,
            'selectedYear'   => $selectedYear,
            'yearRoute'      => route('home'),
            'incomeTotal'    => $incomeTotal,
            'expenseTotal'   => $expenseTotal,
            'balanceTotal'   => $incomeTotal - $expenseTotal,
            'movementsCount' => $movementsCount,
            'recentEntries'  => $recentEntries,
        ]);
    }
}
