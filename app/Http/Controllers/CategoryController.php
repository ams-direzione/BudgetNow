<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\EntryType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request, EntryType $tipo)
    {
        $this->ensureInCurrentBudget($tipo);
        $budgetId = $this->currentBudgetId();
        $sortDir = $request->query('dir') === 'desc' ? 'desc' : 'asc';
        $perPage = (int) $request->query('per_page', 20);
        $perPage = in_array($perPage, [10, 20, 50, 100, 0]) ? $perPage : 20;
        $filters = $request->query('filters', []);
        $nameFilter = trim((string) ($filters['category'] ?? $request->query('search', '')));

        // Carica ad albero: radici ordinate + figli ordinati per ciascuna
        $query = Category::with([
                'children' => fn ($q) => $q
                    ->withCount('journalEntries')
                    ->orderBy('name', $sortDir),
            ])
            ->withCount('journalEntries')
            ->whereNull('parent_id')
            ->where('budget_id', $budgetId)
            ->where('entry_type_id', $tipo->id)
            ->orderBy('name', $sortDir);

        if ($nameFilter !== '') {
            $query->where(function ($q) use ($nameFilter) {
                $q->where('name', 'LIKE', '%' . $nameFilter . '%')
                    ->orWhereHas('children', fn ($c) => $c->where('name', 'LIKE', '%' . $nameFilter . '%'));
            });
        }

        $rootCategories = $query->paginate($perPage ?: 9999)->withQueryString();

        // Categorie radice per il select "Genitore" nell'edit inline
        $parents = Category::whereNull('parent_id')
            ->where('budget_id', $budgetId)
            ->where('entry_type_id', $tipo->id)
            ->orderBy('name')
            ->get();

        return view('categorie.index', compact('tipo', 'rootCategories', 'parents', 'sortDir', 'filters', 'perPage'));
    }

    public function create(Request $request)
    {
        $budgetId = $this->currentBudgetId();
        $entryTypes = EntryType::where('budget_id', $budgetId)->orderBy('name')->get();
        $tipoId     = $request->query('tipo');
        $tipo       = $tipoId
            ? EntryType::where('budget_id', $budgetId)->find($tipoId)
            : $entryTypes->first();

        $parents = $tipo
            ? Category::where('budget_id', $budgetId)->whereNull('parent_id')->where('entry_type_id', $tipo->id)->orderBy('name')->get()
            : collect();

        return view('categorie.create', compact('entryTypes', 'tipo', 'parents'));
    }

    public function store(Request $request)
    {
        $budgetId = $this->currentBudgetId();
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'entry_type_id' => ['required', Rule::exists('entry_types', 'id')->where(fn ($q) => $q->where('budget_id', $budgetId))],
            'parent_id'     => ['nullable', Rule::exists('categories', 'id')->where(fn ($q) => $q->where('budget_id', $budgetId))],
        ], [
            'name.required'          => 'Il nome è obbligatorio.',
            'entry_type_id.required' => 'Il tipo è obbligatorio.',
        ]);

        $categoria = Category::create([
            'budget_id' => $budgetId,
            ...$data,
        ]);
        $tipo = EntryType::where('budget_id', $budgetId)->findOrFail($data['entry_type_id']);

        return redirect()
            ->route('categorie.index', $tipo)
            ->with('success', 'Categoria «' . $categoria->name . '» aggiunta con successo.');
    }

    public function edit(Category $categoria)
    {
        $this->ensureInCurrentBudget($categoria);
        $budgetId = $this->currentBudgetId();

        $entryTypes = EntryType::where('budget_id', $budgetId)->orderBy('name')->get();
        $parents    = Category::whereNull('parent_id')
            ->where('budget_id', $budgetId)
            ->where('entry_type_id', $categoria->entry_type_id)
            ->where('id', '!=', $categoria->id)
            ->orderBy('name')
            ->get();

        return view('categorie.edit', compact('categoria', 'entryTypes', 'parents'));
    }

    public function update(Request $request, Category $categoria)
    {
        $this->ensureInCurrentBudget($categoria);
        $budgetId = $this->currentBudgetId();

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'entry_type_id' => ['required', Rule::exists('entry_types', 'id')->where(fn ($q) => $q->where('budget_id', $budgetId))],
            'parent_id'     => ['nullable', Rule::exists('categories', 'id')->where(fn ($q) => $q->where('budget_id', $budgetId))],
        ], [
            'name.required'          => 'Il nome è obbligatorio.',
            'entry_type_id.required' => 'Il tipo è obbligatorio.',
        ]);

        $categoria->update($data);
        $categoria->load('parent', 'entryType');

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'row'     => $this->categoriaToRow($categoria),
            ]);
        }

        $tipo = EntryType::where('budget_id', $budgetId)->findOrFail($data['entry_type_id']);

        return redirect()
            ->route('categorie.index', $tipo)
            ->with('success', 'Categoria «' . $categoria->name . '» aggiornata con successo.');
    }

    public function destroy(Category $categoria)
    {
        $this->ensureInCurrentBudget($categoria);
        try {
            $tipoId = $categoria->entry_type_id;
            $categoria->delete();

            if (request()->wantsJson()) {
                return response()->json(['success' => true]);
            }

            $tipo = EntryType::find($tipoId);

            return redirect()
                ->route('categorie.index', $tipo)
                ->with('success', 'Categoria eliminata con successo.');
        } catch (\Illuminate\Database\QueryException) {
            if (request()->wantsJson()) {
                return response()->json(['error' => 'Impossibile eliminare: è utilizzata in uno o più movimenti.'], 409);
            }

            return back()->with('error', 'Impossibile eliminare la categoria: è utilizzata in uno o più movimenti.');
        }
    }

    private function categoriaToRow(Category $categoria): array
    {
        return [
            'id'             => $categoria->id,
            'name'           => $categoria->name,
            'entry_type_id'  => (string) $categoria->entry_type_id,
            'parent_id'      => $categoria->parent_id ? (string) $categoria->parent_id : '',
            'parent_name'    => $categoria->parent?->name ?? '',
        ];
    }
}
