<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\EntryType;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CorrenteController extends Controller
{
    public function entrate(Request $request)
    {
        return $this->renderTab($request, 'Entrata', 'entrate');
    }

    public function uscite(Request $request)
    {
        return $this->renderTab($request, 'Uscita', 'uscite');
    }

    private function renderTab(Request $request, string $entryTypeName, string $activeTab)
    {
        $search = trim((string) $request->query('search', ''));
        $searchLower = mb_strtolower($search);
        $allowedSort = ['category', 'sub_category', 'subtotal'];
        $sortField = in_array($request->query('sort'), $allowedSort, true) ? $request->query('sort') : 'category';
        $sortDir = $request->query('dir') === 'desc' ? 'desc' : 'asc';

        $budgetId = $this->currentBudgetId();
        $availableYears = $this->availableYearsFromDb($budgetId);
        $selectedYear = $this->resolveSelectedYear($request, $availableYears);

        $entryType = EntryType::where('budget_id', $budgetId)->where('name', $entryTypeName)->first();

        if (! $entryType) {
            return view('corrente.index', [
                'activeTab' => $activeTab,
                'categories' => collect(),
                'grandTotal' => 0.0,
                'currentYear' => $selectedYear,
                'previousYear' => $selectedYear - 1,
                'twoYearsAgoYear' => $selectedYear - 2,
                'availableYears' => $availableYears,
                'selectedYear' => $selectedYear,
                'yearRoute' => route('corrente.' . $activeTab),
                'sortField' => $sortField,
                'sortDir' => $sortDir,
                'search' => $search,
            ]);
        }

        $currentYear = $selectedYear;
        $previousYear = $currentYear - 1;
        $twoYearsAgoYear = $currentYear - 2;

        $subtotalsByCategory = JournalEntry::query()
            ->where('budget_id', $budgetId)
            ->where('entry_type_id', $entryType->id)
            ->whereYear('entry_date', $currentYear)
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->selectRaw('category_id, SUM(amount) as subtotal')
            ->pluck('subtotal', 'category_id');

        $monthlyByCategoryCurrent = JournalEntry::query()
            ->where('budget_id', $budgetId)
            ->where('entry_type_id', $entryType->id)
            ->whereYear('entry_date', $currentYear)
            ->whereNotNull('category_id')
            ->selectRaw('category_id, MONTH(entry_date) as month_num, SUM(amount) as subtotal')
            ->groupBy('category_id', DB::raw('MONTH(entry_date)'))
            ->get()
            ->groupBy('category_id')
            ->map(function ($rows) {
                $months = array_fill(1, 12, 0.0);
                foreach ($rows as $row) {
                    $months[(int) $row->month_num] = (float) $row->subtotal;
                }

                return array_values($months);
            });

        $monthlyByCategoryPrevious = JournalEntry::query()
            ->where('budget_id', $budgetId)
            ->where('entry_type_id', $entryType->id)
            ->whereYear('entry_date', $previousYear)
            ->whereNotNull('category_id')
            ->selectRaw('category_id, MONTH(entry_date) as month_num, SUM(amount) as subtotal')
            ->groupBy('category_id', DB::raw('MONTH(entry_date)'))
            ->get()
            ->groupBy('category_id')
            ->map(function ($rows) {
                $months = array_fill(1, 12, 0.0);
                foreach ($rows as $row) {
                    $months[(int) $row->month_num] = (float) $row->subtotal;
                }

                return array_values($months);
            });

        $monthlyByCategoryTwoYearsAgo = JournalEntry::query()
            ->where('budget_id', $budgetId)
            ->where('entry_type_id', $entryType->id)
            ->whereYear('entry_date', $twoYearsAgoYear)
            ->whereNotNull('category_id')
            ->selectRaw('category_id, MONTH(entry_date) as month_num, SUM(amount) as subtotal')
            ->groupBy('category_id', DB::raw('MONTH(entry_date)'))
            ->get()
            ->groupBy('category_id')
            ->map(function ($rows) {
                $months = array_fill(1, 12, 0.0);
                foreach ($rows as $row) {
                    $months[(int) $row->month_num] = (float) $row->subtotal;
                }

                return array_values($months);
            });

        $rootCategories = Category::query()
            ->with([
                'children' => fn ($q) => $q->orderBy('sort_order')->orderBy('name'),
            ])
            ->where('budget_id', $budgetId)
            ->where('entry_type_id', $entryType->id)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $categories = collect();
        $grandTotal = 0.0;

        foreach ($rootCategories as $root) {
            $subRows = collect();
            $categoryMonths = array_fill(0, 12, 0.0);
            $categoryMonthsPrevious = array_fill(0, 12, 0.0);
            $categoryMonthsTwoYearsAgo = array_fill(0, 12, 0.0);
            $rootMatch = $searchLower !== '' && str_contains(mb_strtolower($root->name), $searchLower);

            if ($root->children->isEmpty()) {
                if ($searchLower !== '' && ! $rootMatch) {
                    continue;
                }

                $months = $monthlyByCategoryCurrent[$root->id] ?? array_fill(0, 12, 0.0);
                $monthsPrevious = $monthlyByCategoryPrevious[$root->id] ?? array_fill(0, 12, 0.0);
                $monthsTwoYearsAgo = $monthlyByCategoryTwoYearsAgo[$root->id] ?? array_fill(0, 12, 0.0);
                $subRows->push([
                    'id' => $root->id,
                    'name' => '—',
                    'subtotal' => (float) ($subtotalsByCategory[$root->id] ?? 0),
                    'months' => $months,
                    'months_previous' => $monthsPrevious,
                    'months_two_years_ago' => $monthsTwoYearsAgo,
                ]);
                $categoryMonths = $months;
                $categoryMonthsPrevious = $monthsPrevious;
                $categoryMonthsTwoYearsAgo = $monthsTwoYearsAgo;
            } else {
                foreach ($root->children as $child) {
                    $childMatch = $searchLower !== '' && str_contains(mb_strtolower($child->name), $searchLower);
                    if ($searchLower !== '' && ! $rootMatch && ! $childMatch) {
                        continue;
                    }

                    $months = $monthlyByCategoryCurrent[$child->id] ?? array_fill(0, 12, 0.0);
                    $monthsPrevious = $monthlyByCategoryPrevious[$child->id] ?? array_fill(0, 12, 0.0);
                    $monthsTwoYearsAgo = $monthlyByCategoryTwoYearsAgo[$child->id] ?? array_fill(0, 12, 0.0);
                    for ($i = 0; $i < 12; $i++) {
                        $categoryMonths[$i] += $months[$i];
                        $categoryMonthsPrevious[$i] += $monthsPrevious[$i];
                        $categoryMonthsTwoYearsAgo[$i] += $monthsTwoYearsAgo[$i];
                    }

                    $subRows->push([
                        'id' => $child->id,
                        'name' => $child->name,
                        'subtotal' => (float) ($subtotalsByCategory[$child->id] ?? 0),
                        'months' => $months,
                        'months_previous' => $monthsPrevious,
                        'months_two_years_ago' => $monthsTwoYearsAgo,
                    ]);
                }

                if ($searchLower !== '' && $subRows->isEmpty()) {
                    continue;
                }
            }

            $categoryTotal = (float) $subRows->sum('subtotal');
            $grandTotal += $categoryTotal;

            if ($root->children->isNotEmpty() && in_array($sortField, ['sub_category', 'subtotal'], true)) {
                $subRows = $sortField === 'sub_category'
                    ? $subRows->sortBy(fn ($sub) => mb_strtolower((string) ($sub['name'] ?? '')), SORT_NATURAL)
                    : $subRows->sortBy('subtotal');
                if ($sortDir === 'desc') {
                    $subRows = $subRows->reverse();
                }
                $subRows = $subRows->values();
            }

            $categories->push([
                'id' => $root->id,
                'name' => $root->name,
                'sub_rows' => $subRows,
                'total' => $categoryTotal,
                'months' => $categoryMonths,
                'months_previous' => $categoryMonthsPrevious,
                'months_two_years_ago' => $categoryMonthsTwoYearsAgo,
            ]);
        }

        if ($sortField === 'category') {
            $categories = $categories->sortBy(fn ($category) => mb_strtolower((string) $category['name']), SORT_NATURAL);
            if ($sortDir === 'desc') {
                $categories = $categories->reverse();
            }
            $categories = $categories->values();
        } elseif ($sortField === 'subtotal') {
            $categories = $categories->sortBy('total');
            if ($sortDir === 'desc') {
                $categories = $categories->reverse();
            }
            $categories = $categories->values();
        }

        return view('corrente.index', [
            'activeTab' => $activeTab,
            'categories' => $categories,
            'grandTotal' => $grandTotal,
            'currentYear' => $currentYear,
            'previousYear' => $previousYear,
            'twoYearsAgoYear' => $twoYearsAgoYear,
            'availableYears' => $availableYears,
            'selectedYear' => $selectedYear,
            'yearRoute' => route('corrente.' . $activeTab),
            'sortField' => $sortField,
            'sortDir' => $sortDir,
            'search' => $search,
        ]);
    }
}
