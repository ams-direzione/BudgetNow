<?php

namespace App\Http\Controllers;

use App\Models\ReferenceAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReferenceAccountController extends Controller
{
    public function index(Request $request)
    {
        $budgetId = $this->currentBudgetId();
        $allowed   = ['name', 'account_code', 'bank_name'];
        $sortField = in_array($request->query('sort'), $allowed) ? $request->query('sort') : 'name';
        $sortDir   = $request->query('dir') === 'desc' ? 'desc' : 'asc';
        $filters   = $request->query('filters', []);
        $nameFilter = trim((string) ($filters['name'] ?? ''));
        $codeFilter = trim((string) ($filters['account_code'] ?? ''));
        $bankFilter = trim((string) ($filters['bank_name'] ?? $request->query('search', '')));
        $perPage   = (int) $request->query('per_page', 20);
        $perPage   = in_array($perPage, [10, 20, 50, 100, 0]) ? $perPage : 20;

        $query = ReferenceAccount::where('budget_id', $budgetId)->orderBy($sortField, $sortDir);

        if ($nameFilter !== '') {
            $query->where('name', 'LIKE', '%' . $nameFilter . '%');
        }
        if ($codeFilter !== '') {
            $query->where('account_code', 'LIKE', '%' . $codeFilter . '%');
        }
        if ($bankFilter !== '') {
            $query->where('bank_name', 'LIKE', '%' . $bankFilter . '%');
        }

        $accounts = $query->paginate($perPage ?: 9999)->withQueryString();

        return view('conti-riferimento.index', compact('accounts', 'sortField', 'sortDir', 'filters', 'perPage'));
    }

    public function create()
    {
        return view('conti-riferimento.create');
    }

    public function store(Request $request)
    {
        $budgetId = $this->currentBudgetId();

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:150'],
            'account_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('reference_accounts', 'account_code')->where(fn ($q) => $q->where('budget_id', $budgetId)),
            ],
            'bank_name'    => ['nullable', 'string', 'max:150'],
        ], [
            'name.required'         => 'Il nome è obbligatorio.',
            'account_code.required' => 'Il codice conto è obbligatorio.',
            'account_code.unique'   => 'Esiste già un conto con questo codice.',
        ]);

        ReferenceAccount::create([
            'budget_id' => $budgetId,
            ...$data,
        ]);

        return redirect()->route('conti-riferimento.index')->with('success', 'Conto di riferimento aggiunto con successo.');
    }

    public function edit(ReferenceAccount $contiRiferimento)
    {
        $this->ensureInCurrentBudget($contiRiferimento);
        return view('conti-riferimento.edit', ['account' => $contiRiferimento]);
    }

    public function update(Request $request, ReferenceAccount $contiRiferimento)
    {
        $this->ensureInCurrentBudget($contiRiferimento);
        $budgetId = $this->currentBudgetId();

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:150'],
            'account_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('reference_accounts', 'account_code')
                    ->where(fn ($q) => $q->where('budget_id', $budgetId))
                    ->ignore($contiRiferimento->id),
            ],
            'bank_name'    => ['nullable', 'string', 'max:150'],
        ], [
            'name.required'         => 'Il nome è obbligatorio.',
            'account_code.required' => 'Il codice conto è obbligatorio.',
            'account_code.unique'   => 'Esiste già un conto con questo codice.',
        ]);

        $contiRiferimento->update($data);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'row'     => [
                    'id'           => $contiRiferimento->id,
                    'name'         => $contiRiferimento->name,
                    'account_code' => $contiRiferimento->account_code,
                    'bank_name'    => $contiRiferimento->bank_name ?? '',
                ],
            ]);
        }

        return redirect()->route('conti-riferimento.index')->with('success', 'Conto di riferimento aggiornato con successo.');
    }

    public function destroy(ReferenceAccount $contiRiferimento)
    {
        $this->ensureInCurrentBudget($contiRiferimento);
        try {
            $contiRiferimento->delete();

            if (request()->wantsJson()) {
                return response()->json(['success' => true]);
            }

            return redirect()->route('conti-riferimento.index')->with('success', 'Conto di riferimento eliminato con successo.');
        } catch (\Illuminate\Database\QueryException) {
            if (request()->wantsJson()) {
                return response()->json(['error' => 'Impossibile eliminare: è utilizzato in uno o più movimenti.'], 409);
            }

            return redirect()->route('conti-riferimento.index')->with('error', 'Impossibile eliminare il conto: è utilizzato in uno o più movimenti.');
        }
    }
}
