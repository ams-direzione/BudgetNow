<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\Category;
use App\Models\EntryType;
use App\Services\CurrentBudget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BudgetController extends Controller
{
    public function index()
    {
        $request = request();
        $sortField = in_array($request->query('sort'), ['name']) ? $request->query('sort') : 'name';
        $sortDir = $request->query('dir') === 'desc' ? 'desc' : 'asc';
        $perPage = (int) $request->query('per_page', 20);
        $perPage = in_array($perPage, [10, 20, 50, 100, 0]) ? $perPage : 20;
        $filters = $request->query('filters', []);
        $nameFilter = trim((string) ($filters['name'] ?? ''));

        $budgets = $this->accessibleBudgetsQuery()->orderBy($sortField, $sortDir);
        if ($nameFilter !== '') {
            $budgets->where('name', 'LIKE', '%' . $nameFilter . '%');
        }

        $budgets = $budgets->paginate($perPage ?: 9999)->withQueryString();
        $activeBudgetId = $this->currentBudgetId();

        return view('budget.index', compact('budgets', 'activeBudgetId', 'sortField', 'sortDir', 'filters', 'perPage'));
    }

    public function create()
    {
        return view('budget.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ], [
            'name.required' => 'Il nome budget è obbligatorio.',
        ]);

        $budget = Budget::create([
            'user_id' => auth()->id(),
            'name' => $data['name'],
        ]);

        app(CurrentBudget::class)->set($budget);

        return redirect()->route('budget.index')->with('success', 'Budget creato con successo.');
    }

    public function edit(Budget $budget)
    {
        abort_unless(app(CurrentBudget::class)->isAccessible($budget), 404);

        return view('budget.edit', compact('budget'));
    }

    public function update(Request $request, Budget $budget): RedirectResponse
    {
        abort_unless(app(CurrentBudget::class)->isAccessible($budget), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ], [
            'name.required' => 'Il nome budget è obbligatorio.',
        ]);

        $budget->update($data);

        return redirect()->route('budget.index')->with('success', 'Budget aggiornato con successo.');
    }

    public function destroy(Budget $budget): RedirectResponse
    {
        abort_unless(app(CurrentBudget::class)->isAccessible($budget), 404);

        $allBudgets = $this->accessibleBudgetsQuery()->orderBy('name')->get();

        if ($allBudgets->count() <= 1) {
            return back()->with('error', 'Non puoi eliminare l\'unico budget disponibile.');
        }

        $fallback = $allBudgets->firstWhere('id', '!=', $budget->id);

        DB::transaction(function () use ($budget, $fallback) {
            if ((int) session('active_budget_id') === (int) $budget->id && $fallback) {
                session(['active_budget_id' => $fallback->id]);
            }

            $budget->delete();
        });

        return redirect()->route('budget.index')->with('success', 'Budget eliminato con successo.');
    }

    public function switch(Budget $budget): RedirectResponse
    {
        abort_unless(app(CurrentBudget::class)->isAccessible($budget), 404);

        app(CurrentBudget::class)->set($budget);

        return back()->with('success', 'Budget attivo aggiornato.');
    }

    public function duplicate(Request $request, Budget $budget): RedirectResponse
    {
        abort_unless(app(CurrentBudget::class)->isAccessible($budget), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'mode' => ['required', 'in:empty,categories'],
        ]);

        $newBudget = DB::transaction(function () use ($budget, $data) {
            $clone = Budget::create([
                'user_id' => $budget->user_id,
                'name' => $data['name'],
            ]);

            if ($data['mode'] === 'categories') {
                $typeMap = [];

                $sourceTypes = EntryType::where('budget_id', $budget->id)->orderBy('id')->get();
                foreach ($sourceTypes as $type) {
                    $newType = EntryType::create([
                        'budget_id' => $clone->id,
                        'name' => $type->name,
                    ]);
                    $typeMap[$type->id] = $newType->id;
                }

                $catMap = [];

                $sourceRoots = Category::where('budget_id', $budget->id)
                    ->whereNull('parent_id')
                    ->orderBy('id')
                    ->get();

                foreach ($sourceRoots as $root) {
                    $newRoot = Category::create([
                        'budget_id' => $clone->id,
                        'name' => $root->name,
                        'parent_id' => null,
                        'entry_type_id' => $typeMap[$root->entry_type_id] ?? null,
                    ]);

                    $catMap[$root->id] = $newRoot->id;
                }

                $sourceChildren = Category::where('budget_id', $budget->id)
                    ->whereNotNull('parent_id')
                    ->orderBy('id')
                    ->get();

                foreach ($sourceChildren as $child) {
                    $newChild = Category::create([
                        'budget_id' => $clone->id,
                        'name' => $child->name,
                        'parent_id' => $catMap[$child->parent_id] ?? null,
                        'entry_type_id' => $typeMap[$child->entry_type_id] ?? null,
                    ]);

                    $catMap[$child->id] = $newChild->id;
                }
            }

            return $clone;
        });

        app(CurrentBudget::class)->set($newBudget);

        return redirect()->route('budget.index')->with('success', 'Budget duplicato con successo.');
    }

    private function accessibleBudgetsQuery()
    {
        $query = Budget::query();
        $userId = auth()->id();

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->whereNull('user_id');
        }

        return $query;
    }
}
