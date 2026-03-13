<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\EntryType;
use App\Models\JournalEntry;
use App\Models\ReferenceAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JournalEntryController extends Controller
{
    public function index(Request $request)
    {
        $budgetId = $this->currentBudgetId();

        $availableYears = JournalEntry::query()
            ->where('budget_id', $budgetId)
            ->selectRaw('YEAR(entry_date) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->all();

        if ($availableYears === []) {
            $availableYears = [(int) now()->format('Y')];
        }

        $selectedYear = (int) $request->query('year', $availableYears[0]);
        if (! in_array($selectedYear, $availableYears, true)) {
            $selectedYear = $availableYears[0];
        }

        $allowedSort = ['entry_date', 'movement_number', 'amount'];
        $sortField   = in_array($request->query('sort'), $allowedSort) ? $request->query('sort') : 'entry_date';
        $sortDir     = $request->query('dir') === 'desc' ? 'desc' : 'asc';
        $filters     = $request->query('filters', []);
        $search      = trim($request->query('search', ''));
        $movementFilter = trim((string) ($filters['movement_number'] ?? ''));
        $dateFilter     = trim((string) ($filters['entry_date'] ?? ''));
        $typeFilter     = trim((string) ($filters['entry_type'] ?? ''));
        $categoryFilter = trim((string) ($filters['category'] ?? ''));
        $subCategoryFilter = trim((string) ($filters['sub_category'] ?? ''));
        $descriptionFilter = trim((string) ($filters['description'] ?? $search));
        $amountFilter   = trim((string) ($filters['amount'] ?? ''));
        $accountFilter  = trim((string) ($filters['account'] ?? ''));
        $perPage     = (int) $request->query('per_page', 20);
        $perPage     = in_array($perPage, [10, 20, 50, 100, 0]) ? $perPage : 20;

        $dateFrom = trim($request->query('date_from', ''));
        $dateTo   = trim($request->query('date_to', ''));

        $query = JournalEntry::query()
            ->where('budget_id', $budgetId)
            ->with(['referenceAccount', 'entryType', 'category.parent'])
            ->orderBy($sortField, $sortDir)
            ->orderBy('movement_number');

        if ($dateFrom !== '' || $dateTo !== '') {
            if ($dateFrom !== '') $query->where('entry_date', '>=', $dateFrom);
            if ($dateTo !== '')   $query->where('entry_date', '<=', $dateTo);
        } else {
            $query->whereBetween('entry_date', ["{$selectedYear}-01-01", "{$selectedYear}-12-31"]);
        }

        if ($movementFilter !== '') {
            $query->where('movement_number', 'LIKE', '%' . $movementFilter . '%');
        }
        if ($dateFilter !== '') {
            $query->whereDate('entry_date', $dateFilter);
        }
        if ($typeFilter !== '') {
            $query->whereHas('entryType', fn ($t) => $t->where('name', 'LIKE', '%' . $typeFilter . '%'));
        }
        if ($categoryFilter !== '') {
            $query->whereHas('category', fn ($c) => $c->whereNull('parent_id')->where('name', 'LIKE', '%' . $categoryFilter . '%'));
        }
        if ($subCategoryFilter !== '') {
            $query->whereHas('category', fn ($c) => $c->whereNotNull('parent_id')->where('name', 'LIKE', '%' . $subCategoryFilter . '%'));
        }
        if ($descriptionFilter !== '') {
            $query->where('description', 'LIKE', '%' . $descriptionFilter . '%');
        }
        if ($amountFilter !== '') {
            $normalizedAmount = str_replace(',', '.', str_replace('.', '', $amountFilter));
            if (is_numeric($normalizedAmount)) {
                $query->where('amount', (float) $normalizedAmount);
            }
        }
        if ($accountFilter !== '') {
            $query->whereHas('referenceAccount', fn ($a) => $a->where('name', 'LIKE', '%' . $accountFilter . '%'));
        }

        $entries = $query->paginate($perPage ?: 9999)->withQueryString();

        return view('journal.index', [
            'availableYears' => $availableYears,
            'selectedYear'   => $selectedYear,
            'yearRoute'      => route('journal.index'),
            'entries'        => $entries,
            'sortField'      => $sortField,
            'sortDir'        => $sortDir,
            'search'         => $search,
            'filters'        => $filters,
            'perPage'        => $perPage,
            'dateFrom'       => $dateFrom,
            'dateTo'         => $dateTo,
            'entryTypes'     => EntryType::where('budget_id', $budgetId)->orderBy('name')->get(),
            'categories'     => Category::where('budget_id', $budgetId)->orderBy('name')->get(),
            'accounts'       => ReferenceAccount::where('budget_id', $budgetId)->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('journal.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules(), $this->messages());
        $data['budget_id'] = $this->currentBudgetId();

        $entry = JournalEntry::create($data);
        $entry->load(['entryType', 'category.parent', 'referenceAccount']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'entry'   => $this->entryToJson($entry),
                'year'    => $entry->entry_date->year,
            ]);
        }

        return redirect()
            ->route('journal.index', ['year' => $entry->entry_date->year])
            ->with('success', 'Movimento «' . $entry->movement_number . '» aggiunto con successo.');
    }

    public function edit(JournalEntry $entry)
    {
        $this->ensureInCurrentBudget($entry);
        return view('journal.edit', array_merge($this->formData(), compact('entry')));
    }

    public function update(Request $request, JournalEntry $entry)
    {
        $this->ensureInCurrentBudget($entry);
        $data = $request->validate($this->rules($entry->id), $this->messages());

        $entry->update($data);
        $entry->refresh()->load(['entryType', 'category.parent', 'referenceAccount']);

        if ($request->wantsJson()) {
            $data = $this->entryToJson($entry);
            return response()->json([
                'success' => true,
                'row'     => $data,
                'entry'   => $data,
            ]);
        }

        return redirect()
            ->route('journal.index', ['year' => $entry->entry_date->year])
            ->with('success', 'Movimento «' . $entry->movement_number . '» aggiornato con successo.');
    }

    public function destroy(JournalEntry $entry)
    {
        $this->ensureInCurrentBudget($entry);
        $year   = $entry->entry_date->year;
        $number = $entry->movement_number;

        $entry->delete();

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()
            ->route('journal.index', ['year' => $year])
            ->with('success', 'Movimento «' . $number . '» eliminato con successo.');
    }

    private function entryToJson(JournalEntry $entry): array
    {
        $cat    = $entry->category;
        $parent = $cat?->parent;

        return [
            'id'                   => $entry->id,
            'movement_number'      => $entry->movement_number,
            'entry_date'           => $entry->entry_date->format('Y-m-d'),
            'entry_date_formatted' => $entry->entry_date->format('d/m/Y'),
            'entry_type_id'        => (string) $entry->entry_type_id,
            'entry_type_name'      => $entry->entryType?->name ?? '',
            'category_id'          => $entry->category_id ? (string) $entry->category_id : '',
            'category_name'        => $parent ? ($parent->name) : ($cat?->name ?? ''),
            'sub_category_name'    => $parent ? ($cat->name) : '',
            'description'          => $entry->description ?? '',
            'amount'               => (float) $entry->amount,
            'amount_formatted'     => number_format((float) $entry->amount, 2, ',', '.'),
            'reference_account_id' => (string) $entry->reference_account_id,
            'account_name'         => $entry->referenceAccount?->name ?? '',
            'account_edit_url'     => route('conti-riferimento.edit', $entry->reference_account_id),
        ];
    }

    private function formData(): array
    {
        $budgetId = $this->currentBudgetId();

        return [
            'entryTypes' => EntryType::where('budget_id', $budgetId)->orderBy('name')->get(),
            'categories' => Category::where('budget_id', $budgetId)->orderBy('name')->get(),
            'accounts'   => ReferenceAccount::where('budget_id', $budgetId)->orderBy('name')->get(),
        ];
    }

    private function rules(?int $ignoreId = null): array
    {
        $budgetId = $this->currentBudgetId();
        $movementUnique = Rule::unique('journal_entries', 'movement_number')
            ->where(fn ($q) => $q->where('budget_id', $budgetId));
        if ($ignoreId) {
            $movementUnique = $movementUnique->ignore($ignoreId);
        }

        return [
            'movement_number'      => [
                'required',
                'string',
                'max:50',
                $movementUnique,
            ],
            'entry_date'           => ['required', 'date'],
            'entry_type_id'        => ['required', Rule::exists('entry_types', 'id')->where(fn ($q) => $q->where('budget_id', $budgetId))],
            'category_id'          => ['nullable', Rule::exists('categories', 'id')->where(fn ($q) => $q->where('budget_id', $budgetId))],
            'description'          => ['nullable', 'string', 'max:1000'],
            'amount'               => ['required', 'numeric', 'min:0.01'],
            'reference_account_id' => ['required', Rule::exists('reference_accounts', 'id')->where(fn ($q) => $q->where('budget_id', $budgetId))],
        ];
    }

    private function messages(): array
    {
        return [
            'movement_number.required'      => 'Il numero movimento è obbligatorio.',
            'movement_number.unique'        => 'Esiste già un movimento con questo numero.',
            'entry_date.required'           => 'La data è obbligatoria.',
            'entry_type_id.required'        => 'Il tipo è obbligatorio.',
            'amount.required'               => "L'importo è obbligatorio.",
            'amount.min'                    => "L'importo deve essere maggiore di zero.",
            'reference_account_id.required' => 'Il conto di riferimento è obbligatorio.',
        ];
    }
}
