<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\EntryType;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
                    ->orderBy('sort_order')
                    ->orderBy('name', $sortDir),
            ])
            ->withCount('journalEntries')
            ->whereNull('parent_id')
            ->where('budget_id', $budgetId)
            ->where('entry_type_id', $tipo->id)
            ->orderBy('sort_order')
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
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $layoutRoots = Category::query()
            ->where('budget_id', $budgetId)
            ->where('entry_type_id', $tipo->id)
            ->whereNull('parent_id')
            ->withCount('journalEntries')
            ->with([
                'children' => fn ($q) => $q
                    ->withCount('journalEntries')
                    ->orderBy('sort_order')
                    ->orderBy('name'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (Category $root) {
                return [
                    'id' => (int) $root->id,
                    'name' => $root->name,
                    'journal_entries_count' => (int) $root->journal_entries_count,
                    'children' => $root->children->map(fn (Category $child) => [
                        'id' => (int) $child->id,
                        'name' => $child->name,
                        'journal_entries_count' => (int) $child->journal_entries_count,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();

        return view('categorie.index', compact('tipo', 'rootCategories', 'parents', 'sortDir', 'filters', 'perPage', 'layoutRoots'));
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
            ? Category::where('budget_id', $budgetId)->whereNull('parent_id')->where('entry_type_id', $tipo->id)->orderBy('sort_order')->orderBy('name')->get()
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
        $this->assertParentConsistency($budgetId, $data);

        $categoria = Category::create([
            'budget_id' => $budgetId,
            'sort_order' => $this->nextSortOrder(
                $budgetId,
                (int) $data['entry_type_id'],
                isset($data['parent_id']) && $data['parent_id'] !== null ? (int) $data['parent_id'] : null
            ),
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
            ->orderBy('sort_order')
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
        $this->assertParentConsistency($budgetId, $data, $categoria);

        $parentChanged = ((int) ($categoria->parent_id ?? 0)) !== ((int) ($data['parent_id'] ?? 0));
        if ($parentChanged) {
            $data['sort_order'] = $this->nextSortOrder(
                $budgetId,
                (int) $data['entry_type_id'],
                isset($data['parent_id']) && $data['parent_id'] !== null ? (int) $data['parent_id'] : null
            );
        }
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

    public function saveLayout(Request $request, EntryType $tipo)
    {
        $this->ensureInCurrentBudget($tipo);
        $budgetId = $this->currentBudgetId();

        $validated = $request->validate([
            'roots' => ['required', 'array', 'min:1'],
            'roots.*.id' => ['required', 'integer'],
            'roots.*.children' => ['nullable', 'array'],
            'roots.*.children.*' => ['integer'],
        ]);

        $categories = Category::query()
            ->where('budget_id', $budgetId)
            ->where('entry_type_id', $tipo->id)
            ->get(['id', 'name']);

        if ($categories->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Nessuna categoria disponibile.',
            ], 422);
        }

        $validIds = $categories->pluck('id')->map(fn ($v) => (int) $v)->all();
        $validLookup = array_fill_keys($validIds, true);

        $used = [];
        $assignments = [];
        foreach ($validated['roots'] as $rootIndex => $rootData) {
            $rootId = (int) $rootData['id'];
            if (!isset($validLookup[$rootId])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Layout non valido: categoria radice non trovata.',
                ], 422);
            }
            if (isset($used[$rootId])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Layout non valido: categoria duplicata.',
                ], 422);
            }
            $used[$rootId] = true;
            $assignments[$rootId] = [
                'parent_id' => null,
                'sort_order' => $rootIndex + 1,
            ];

            $children = is_array($rootData['children'] ?? null) ? $rootData['children'] : [];
            foreach ($children as $childIndex => $childRaw) {
                $childId = (int) $childRaw;
                if (!isset($validLookup[$childId])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Layout non valido: sottocategoria non trovata.',
                    ], 422);
                }
                if ($childId === $rootId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Layout non valido: una categoria non puo\' essere figlia di se stessa.',
                    ], 422);
                }
                if (isset($used[$childId])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Layout non valido: categoria duplicata.',
                    ], 422);
                }
                $used[$childId] = true;
                $assignments[$childId] = [
                    'parent_id' => $rootId,
                    'sort_order' => $childIndex + 1,
                ];
            }
        }

        if (count($used) !== count($validIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Layout incompleto: alcune categorie non sono state assegnate.',
            ], 422);
        }

        foreach ($assignments as $id => $meta) {
            if ($meta['parent_id'] !== null) {
                $hasChildren = false;
                foreach ($assignments as $childMeta) {
                    if ((int) ($childMeta['parent_id'] ?? 0) === (int) $id) {
                        $hasChildren = true;
                        break;
                    }
                }
                if ($hasChildren) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Layout non valido: una sottocategoria non puo\' avere ulteriori figli.',
                    ], 422);
                }
            }
        }

        DB::transaction(function () use ($assignments, $budgetId, $tipo) {
            foreach ($assignments as $id => $meta) {
                Category::query()
                    ->where('budget_id', $budgetId)
                    ->where('entry_type_id', $tipo->id)
                    ->where('id', $id)
                    ->update([
                        'parent_id' => $meta['parent_id'],
                        'sort_order' => (int) $meta['sort_order'],
                    ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Layout categorie salvato. Le registrazioni del Libro Giornale non vengono modificate.',
        ]);
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

    private function assertParentConsistency(int $budgetId, array $data, ?Category $categoria = null): void
    {
        $parentId = isset($data['parent_id']) && $data['parent_id'] !== null ? (int) $data['parent_id'] : null;
        if ($parentId === null) {
            return;
        }

        if ($categoria && $parentId === (int) $categoria->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'La categoria non puo\' avere se stessa come genitore.',
            ]);
        }

        $entryTypeId = (int) $data['entry_type_id'];
        $parent = Category::query()
            ->where('budget_id', $budgetId)
            ->where('id', $parentId)
            ->first();

        if (!$parent) {
            throw ValidationException::withMessages([
                'parent_id' => 'Genitore non valido.',
            ]);
        }
        if ((int) $parent->entry_type_id !== $entryTypeId) {
            throw ValidationException::withMessages([
                'parent_id' => 'Il genitore deve appartenere allo stesso tipo di movimento.',
            ]);
        }
        if ($parent->parent_id !== null) {
            throw ValidationException::withMessages([
                'parent_id' => 'Il genitore selezionato deve essere una categoria radice.',
            ]);
        }
        if ($categoria && $categoria->children()->exists()) {
            throw ValidationException::withMessages([
                'parent_id' => 'Una categoria con sottocategorie non puo\' diventare sottocategoria.',
            ]);
        }
    }

    private function nextSortOrder(int $budgetId, int $entryTypeId, ?int $parentId): int
    {
        $max = Category::query()
            ->where('budget_id', $budgetId)
            ->where('entry_type_id', $entryTypeId)
            ->where('parent_id', $parentId)
            ->max('sort_order');

        return ((int) $max) + 1;
    }
}
