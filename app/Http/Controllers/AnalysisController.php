<?php

namespace App\Http\Controllers;

use App\Models\EntryType;
use App\Models\JournalEntry;
use Illuminate\Http\Request;

class AnalysisController extends Controller
{
    public function flows(Request $request)
    {
        $budgetId = $this->currentBudgetId();

        $availableYears = $this->availableYearsFromDb($budgetId);
        $selectedYear = $this->resolveSelectedYear($request, $availableYears);

        $monthParam = $request->query('month');
        $selectedMonth = is_numeric($monthParam) ? (int) $monthParam : null;
        if ($selectedMonth !== null && ($selectedMonth < 1 || $selectedMonth > 12)) {
            $selectedMonth = null;
        }

        $incomeTypeId = EntryType::query()
            ->where('budget_id', $budgetId)
            ->where('name', 'Entrata')
            ->value('id');

        $expenseTypeId = EntryType::query()
            ->where('budget_id', $budgetId)
            ->where('name', 'Uscita')
            ->value('id');

        $baseQuery = JournalEntry::query()
            ->where('budget_id', $budgetId)
            ->whereYear('entry_date', $selectedYear)
            ->when($selectedMonth, fn ($q) => $q->whereMonth('entry_date', $selectedMonth));

        $incomeTotal = $incomeTypeId
            ? (float) (clone $baseQuery)->where('entry_type_id', $incomeTypeId)->sum('amount')
            : 0.0;

        $expenseTotal = $expenseTypeId
            ? (float) (clone $baseQuery)->where('entry_type_id', $expenseTypeId)->sum('amount')
            : 0.0;

        $balance = $incomeTotal - $expenseTotal;

        return view('analisi.flussi', [
            'availableYears' => $availableYears,
            'selectedYear' => $selectedYear,
            'yearRoute' => route('analysis.flows'),
            'selectedMonth' => $selectedMonth,
            'incomeTotal' => $incomeTotal,
            'expenseTotal' => $expenseTotal,
            'balance' => $balance,
        ]);
    }
}
