<?php

namespace App\Http\Controllers;

use App\Models\EntryType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EntryTypeController extends Controller
{
    public function index(Request $request)
    {
        $budgetId = $this->currentBudgetId();
        $sortField = in_array($request->query('sort'), ['name']) ? $request->query('sort') : 'name';
        $sortDir   = $request->query('dir') === 'desc' ? 'desc' : 'asc';
        $filters   = $request->query('filters', []);
        $nameFilter = trim((string) ($filters['name'] ?? $request->query('search', '')));
        $perPage   = (int) $request->query('per_page', 20);
        $perPage   = in_array($perPage, [10, 20, 50, 100, 0]) ? $perPage : 20;

        $query = EntryType::where('budget_id', $budgetId)->orderBy($sortField, $sortDir);

        if ($nameFilter !== '') {
            $query->where('name', 'LIKE', '%' . $nameFilter . '%');
        }

        $types = $query->paginate($perPage ?: 9999)->withQueryString();

        return view('tipi.index', compact('types', 'sortField', 'sortDir', 'filters', 'perPage'));
    }

    public function create()
    {
        abort(403, 'Creazione tipi disabilitata.');
    }

    public function store(Request $request)
    {
        abort(403, 'Creazione tipi disabilitata.');
    }

    public function edit(EntryType $tipo)
    {
        $this->ensureInCurrentBudget($tipo);
        return view('tipi.edit', compact('tipo'));
    }

    public function update(Request $request, EntryType $tipo)
    {
        $this->ensureInCurrentBudget($tipo);
        $budgetId = $this->currentBudgetId();

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('entry_types', 'name')
                    ->where(fn ($q) => $q->where('budget_id', $budgetId))
                    ->ignore($tipo->id),
            ],
        ], [
            'name.required' => 'Il nome è obbligatorio.',
            'name.unique'   => 'Esiste già un tipo con questo nome.',
        ]);

        $tipo->update($data);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'row'     => ['id' => $tipo->id, 'name' => $tipo->name],
            ]);
        }

        return redirect()->route('tipi.index')->with('success', 'Tipo aggiornato con successo.');
    }

    public function destroy(EntryType $tipo)
    {
        $this->ensureInCurrentBudget($tipo);
        try {
            $tipo->delete();

            if (request()->wantsJson()) {
                return response()->json(['success' => true]);
            }

            return redirect()->route('tipi.index')->with('success', 'Tipo eliminato con successo.');
        } catch (\Illuminate\Database\QueryException) {
            if (request()->wantsJson()) {
                return response()->json(['error' => 'Impossibile eliminare: è utilizzato in movimenti o categorie.'], 409);
            }

            return redirect()->route('tipi.index')->with('error', 'Impossibile eliminare il tipo: è utilizzato in uno o più movimenti.');
        }
    }
}
