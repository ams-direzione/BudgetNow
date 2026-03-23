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

        $availableYears = $this->availableYearsFromDb($budgetId);
        $selectedYear = $this->resolveSelectedYear($request, $availableYears);
        $monthParam = $request->query('month');
        $selectedMonth = is_numeric($monthParam) ? (int) $monthParam : null;
        if ($selectedMonth !== null && ($selectedMonth < 1 || $selectedMonth > 12)) {
            $selectedMonth = null;
        }

        $entrataId = EntryType::where('name', 'Entrata')->value('id');
        $uscitaId  = EntryType::where('name', 'Uscita')->value('id');

        $baseQuery = JournalEntry::query()
            ->where('budget_id', $budgetId)
            ->whereYear('entry_date', $selectedYear);

        $periodQuery = (clone $baseQuery)
            ->when($selectedMonth, fn ($q) => $q->whereMonth('entry_date', $selectedMonth));

        $incomeTotal    = (clone $periodQuery)->where('entry_type_id', $entrataId)->sum('amount');
        $expenseTotal   = (clone $periodQuery)->where('entry_type_id', $uscitaId)->sum('amount');
        $movementsCount = (clone $periodQuery)->count();

        $recentEntries = JournalEntry::query()
            ->with(['referenceAccount', 'entryType', 'category'])
            ->where('budget_id', $budgetId)
            ->whereYear('entry_date', $selectedYear)
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $incomeEntries = JournalEntry::query()
            ->with('category.parent')
            ->where('budget_id', $budgetId)
            ->whereYear('entry_date', $selectedYear)
            ->where('entry_type_id', $entrataId)
            ->when($selectedMonth, fn ($q) => $q->whereMonth('entry_date', $selectedMonth))
            ->get();

        $expenseEntries = JournalEntry::query()
            ->with('category.parent')
            ->where('budget_id', $budgetId)
            ->whereYear('entry_date', $selectedYear)
            ->where('entry_type_id', $uscitaId)
            ->when($selectedMonth, fn ($q) => $q->whereMonth('entry_date', $selectedMonth))
            ->get();

        $incomeCategorySeries = $this->buildCategorySeries($incomeEntries);
        $expenseCategorySeries = $this->buildCategorySeries($expenseEntries);

        return view('home.index', [
            'availableYears' => $availableYears,
            'selectedYear'   => $selectedYear,
            'yearRoute'      => route('home'),
            'selectedMonth'  => $selectedMonth,
            'incomeTotal'    => $incomeTotal,
            'expenseTotal'   => $expenseTotal,
            'balanceTotal'   => $incomeTotal - $expenseTotal,
            'movementsCount' => $movementsCount,
            'recentEntries'  => $recentEntries,
            'incomeByCategoryLabels' => $incomeCategorySeries['labels'],
            'incomeByCategoryValues' => $incomeCategorySeries['values'],
            'incomeSubByCategory' => $incomeCategorySeries['sub_by_category'],
            'incomeEntriesByCategorySub' => $incomeCategorySeries['entries_by_category_sub'],
            'incomeCategorySubCounts' => $incomeCategorySeries['sub_count_by_category'],
            'expenseByCategoryLabels' => $expenseCategorySeries['labels'],
            'expenseByCategoryValues' => $expenseCategorySeries['values'],
            'expenseSubByCategory' => $expenseCategorySeries['sub_by_category'],
            'expenseEntriesByCategorySub' => $expenseCategorySeries['entries_by_category_sub'],
            'expenseCategorySubCounts' => $expenseCategorySeries['sub_count_by_category'],
        ]);
    }

    private function buildCategorySeries($entries): array
    {
        $overview = [];
        $subByCategory = [];
        $entriesByCategorySub = [];

        foreach ($entries as $entry) {
            $category = $entry->category;
            if (! $category) {
                $rootName = 'Senza categoria';
                $subName = 'Senza sub-categoria';
            } else {
                $parent = $category->parent;
                $rootName = $parent ? $parent->name : $category->name;
                $subName = $parent ? $category->name : 'Senza sub-categoria';
            }

            $amount = (float) $entry->amount;
            $overview[$rootName] = ($overview[$rootName] ?? 0.0) + $amount;
            $subByCategory[$rootName][$subName] = ($subByCategory[$rootName][$subName] ?? 0.0) + $amount;

            $entryLabel = trim((string) ($entry->description ?? ''));
            if ($entryLabel === '') {
                $entryLabel = 'Senza descrizione';
            }
            $entriesByCategorySub[$rootName][$subName][$entryLabel] = ($entriesByCategorySub[$rootName][$subName][$entryLabel] ?? 0.0) + $amount;
        }

        arsort($overview);
        foreach ($subByCategory as &$subRows) {
            arsort($subRows);
        }
        foreach ($entriesByCategorySub as &$subRows) {
            foreach ($subRows as &$entryRows) {
                arsort($entryRows);
            }
        }

        return [
            'labels' => array_keys($overview),
            'values' => array_values($overview),
            'sub_by_category' => $subByCategory,
            'entries_by_category_sub' => $entriesByCategorySub,
            'sub_count_by_category' => collect($subByCategory)->map(function ($rows) {
                return collect($rows)->keys()->filter(fn ($name) => $name !== 'Senza sub-categoria')->count();
            })->all(),
        ];
    }
}
