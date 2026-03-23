<?php

namespace App\Http\Controllers;

use App\Models\BudgetForecast;
use App\Models\Category;
use App\Models\EntryType;
use App\Models\JournalEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PreventivoController extends Controller
{
    public function entrate(Request $request)
    {
        return $this->renderTab($request, 'Entrata', 'entrate');
    }

    public function uscite(Request $request)
    {
        return $this->renderTab($request, 'Uscita', 'uscite');
    }

    public function saveEntrate(Request $request): RedirectResponse
    {
        return $this->saveRow($request, 'Entrata', 'entrate');
    }

    public function saveUscite(Request $request): RedirectResponse
    {
        return $this->saveRow($request, 'Uscita', 'uscite');
    }

    private function renderTab(Request $request, string $entryTypeName, string $activeTab)
    {
        $budgetId = $this->currentBudgetId();
        $entryType = EntryType::query()
            ->where('budget_id', $budgetId)
            ->where('name', $entryTypeName)
            ->first();

        $availableYears = $this->availableYearsFromDb($budgetId, includeForecastYears: true);
        $selectedYear = $this->resolveSelectedYear($request, $availableYears);
        $previousYear = $selectedYear - 1;

        if (! $entryType) {
            return view('preventivo.index', [
                'activeTab' => $activeTab,
                'categories' => collect(),
                'grandTotal' => 0.0,
                'currentYear' => $selectedYear,
                'previousYear' => $previousYear,
                'availableYears' => $availableYears,
                'selectedYear' => $selectedYear,
                'yearRoute' => route('preventivo.' . $activeTab),
            ]);
        }

        $forecastsByCategory = BudgetForecast::query()
            ->where('budget_id', $budgetId)
            ->where('entry_type_id', $entryType->id)
            ->where('year', $selectedYear)
            ->get()
            ->keyBy('category_id');

        $previousByCategory = JournalEntry::query()
            ->where('budget_id', $budgetId)
            ->where('entry_type_id', $entryType->id)
            ->whereYear('entry_date', $previousYear)
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->selectRaw('category_id, SUM(amount) as total')
            ->pluck('total', 'category_id');

        $rootCategories = Category::query()
            ->with(['children' => fn ($q) => $q->orderBy('sort_order')->orderBy('name')])
            ->where('budget_id', $budgetId)
            ->where('entry_type_id', $entryType->id)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $categories = collect();
        $grandTotal = 0.0;

        foreach ($rootCategories as $root) {
            $rows = collect();

            if ($root->children->isEmpty()) {
                $forecast = $forecastsByCategory->get($root->id);
                $singleAmount = (float) ($forecast?->single_amount ?? 0);
                $monthsQty = (int) ($forecast?->months_qty ?? 0);
                $totalAmount = (float) ($forecast?->total_amount ?? ($singleAmount * $monthsQty));
                $previousAmount = (float) ($previousByCategory[$root->id] ?? 0);

                $rows->push([
                    'id' => $root->id,
                    'name' => '—',
                    'single_amount' => $singleAmount,
                    'months_qty' => $monthsQty,
                    'total_amount' => $totalAmount,
                    'previous_amount' => $previousAmount,
                ]);
            } else {
                foreach ($root->children as $child) {
                    $forecast = $forecastsByCategory->get($child->id);
                    $singleAmount = (float) ($forecast?->single_amount ?? 0);
                    $monthsQty = (int) ($forecast?->months_qty ?? 0);
                    $totalAmount = (float) ($forecast?->total_amount ?? ($singleAmount * $monthsQty));
                    $previousAmount = (float) ($previousByCategory[$child->id] ?? 0);

                    $rows->push([
                        'id' => $child->id,
                        'name' => $child->name,
                        'single_amount' => $singleAmount,
                        'months_qty' => $monthsQty,
                        'total_amount' => $totalAmount,
                        'previous_amount' => $previousAmount,
                    ]);
                }
            }

            $categoryTotal = (float) $rows->sum('total_amount');
            $categoryPreviousTotal = (float) $rows->sum('previous_amount');
            $grandTotal += $categoryTotal;

            $categories->push([
                'id' => $root->id,
                'name' => $root->name,
                'sub_rows' => $rows,
                'total' => $categoryTotal,
                'previous_total' => $categoryPreviousTotal,
            ]);
        }

        return view('preventivo.index', [
            'activeTab' => $activeTab,
            'categories' => $categories,
            'grandTotal' => $grandTotal,
            'currentYear' => $selectedYear,
            'previousYear' => $previousYear,
            'availableYears' => $availableYears,
            'selectedYear' => $selectedYear,
            'yearRoute' => route('preventivo.' . $activeTab),
        ]);
    }

    private function saveRow(Request $request, string $entryTypeName, string $activeTab): RedirectResponse
    {
        $budgetId = $this->currentBudgetId();
        $entryType = EntryType::query()
            ->where('budget_id', $budgetId)
            ->where('name', $entryTypeName)
            ->firstOrFail();

        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'category_id' => ['required', 'integer'],
            'single_amount' => ['nullable', 'numeric', 'min:0'],
            'months_qty' => ['nullable', 'integer', 'min:0', 'max:12'],
        ]);

        $category = Category::query()
            ->where('budget_id', $budgetId)
            ->where('entry_type_id', $entryType->id)
            ->findOrFail((int) $data['category_id']);

        $singleAmount = round((float) ($data['single_amount'] ?? 0), 2);
        $monthsQty = (int) ($data['months_qty'] ?? 0);
        $totalAmount = round($singleAmount * $monthsQty, 2);

        if ($singleAmount <= 0 || $monthsQty <= 0) {
            BudgetForecast::query()
                ->where('budget_id', $budgetId)
                ->where('entry_type_id', $entryType->id)
                ->where('year', (int) $data['year'])
                ->where('category_id', $category->id)
                ->delete();
        } else {
            BudgetForecast::query()->updateOrCreate(
                [
                    'budget_id' => $budgetId,
                    'entry_type_id' => $entryType->id,
                    'year' => (int) $data['year'],
                    'category_id' => $category->id,
                ],
                [
                    'single_amount' => $singleAmount,
                    'months_qty' => $monthsQty,
                    'total_amount' => $totalAmount,
                ]
            );
        }

        return redirect()
            ->route('preventivo.' . $activeTab, ['year' => (int) $data['year']])
            ->with('success', 'Preventivo aggiornato.');
    }
}
