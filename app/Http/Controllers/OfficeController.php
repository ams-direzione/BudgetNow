<?php

namespace App\Http\Controllers;

use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;

class OfficeController extends Controller
{
    public function index(Request $request)
    {
        if (! Schema::hasTable('offices')) {
            return redirect()->route('journal.index')
                ->with('error', 'Tabella sedi non presente. Esegui le migrazioni.');
        }

        $budgetId = $this->currentBudgetId();
        $allowed = ['name'];
        $sortField = in_array($request->query('sort'), $allowed, true) ? $request->query('sort') : 'name';
        $sortDir = $request->query('dir') === 'desc' ? 'desc' : 'asc';
        $filters = $request->query('filters', []);
        $nameFilter = trim((string) ($filters['name'] ?? $request->query('search', '')));
        $perPage = (int) $request->query('per_page', 20);
        $perPage = in_array($perPage, [10, 20, 50, 100, 0], true) ? $perPage : 20;

        $query = Office::query()->where('budget_id', $budgetId)->orderBy($sortField, $sortDir);

        if ($nameFilter !== '') {
            $query->where('name', 'LIKE', '%' . $nameFilter . '%');
        }

        $offices = $query->paginate($perPage ?: 9999)->withQueryString();

        return view('sedi.index', compact('offices', 'sortField', 'sortDir', 'filters', 'perPage'));
    }

    public function create()
    {
        if (! Schema::hasTable('offices')) {
            return redirect()->route('journal.index')
                ->with('error', 'Tabella sedi non presente. Esegui le migrazioni.');
        }

        return view('sedi.create');
    }

    public function store(Request $request)
    {
        if (! Schema::hasTable('offices')) {
            return redirect()->route('journal.index')
                ->with('error', 'Tabella sedi non presente. Esegui le migrazioni.');
        }

        $budgetId = $this->currentBudgetId();

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('offices', 'name')->where(fn ($q) => $q->where('budget_id', $budgetId)),
            ],
        ], [
            'name.required' => 'Il nome sede è obbligatorio.',
            'name.unique' => 'Esiste già una sede con questo nome.',
        ]);

        Office::create([
            'budget_id' => $budgetId,
            ...$data,
        ]);

        return redirect()->route('sedi.index')->with('success', 'Sede aggiunta con successo.');
    }

    public function edit(Office $sedi)
    {
        if (! Schema::hasTable('offices')) {
            return redirect()->route('journal.index')
                ->with('error', 'Tabella sedi non presente. Esegui le migrazioni.');
        }

        $this->ensureInCurrentBudget($sedi);

        return view('sedi.edit', ['office' => $sedi]);
    }

    public function update(Request $request, Office $sedi)
    {
        if (! Schema::hasTable('offices')) {
            return redirect()->route('journal.index')
                ->with('error', 'Tabella sedi non presente. Esegui le migrazioni.');
        }

        $this->ensureInCurrentBudget($sedi);
        $budgetId = $this->currentBudgetId();

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('offices', 'name')
                    ->where(fn ($q) => $q->where('budget_id', $budgetId))
                    ->ignore($sedi->id),
            ],
        ], [
            'name.required' => 'Il nome sede è obbligatorio.',
            'name.unique' => 'Esiste già una sede con questo nome.',
        ]);

        $sedi->update($data);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'row' => [
                    'id' => $sedi->id,
                    'name' => $sedi->name,
                ],
            ]);
        }

        return redirect()->route('sedi.index')->with('success', 'Sede aggiornata con successo.');
    }

    public function destroy(Office $sedi)
    {
        if (! Schema::hasTable('offices')) {
            return redirect()->route('journal.index')
                ->with('error', 'Tabella sedi non presente. Esegui le migrazioni.');
        }

        $this->ensureInCurrentBudget($sedi);

        try {
            $sedi->delete();

            if (request()->wantsJson()) {
                return response()->json(['success' => true]);
            }

            return redirect()->route('sedi.index')->with('success', 'Sede eliminata con successo.');
        } catch (\Illuminate\Database\QueryException) {
            if (request()->wantsJson()) {
                return response()->json(['error' => 'Impossibile eliminare: è utilizzata in uno o più movimenti.'], 409);
            }

            return redirect()->route('sedi.index')->with('error', 'Impossibile eliminare la sede: è utilizzata in uno o più movimenti.');
        }
    }
}
