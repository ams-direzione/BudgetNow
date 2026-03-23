<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\EntryType;
use App\Models\JournalEntry;
use App\Models\Office;
use App\Models\ReferenceAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;

class JournalEntryController extends Controller
{
    public function index(Request $request)
    {
        $budgetId = $this->currentBudgetId();
        $officeEnabled = Schema::hasTable('offices') && Schema::hasColumn('journal_entries', 'office_id');
        $fieldVisibility = $this->journalFieldVisibility($budgetId);
        $sortSessionKey = "journal.sort.budget.{$budgetId}";

        $availableYears = $this->availableYearsFromDb($budgetId);
        $selectedYear = $this->resolveSelectedYear($request, $availableYears);

        $allowedSort = ['entry_date', 'movement_number', 'amount'];
        $storedSort = (array) $request->session()->get($sortSessionKey, []);
        $requestedSort = $request->query('sort');
        $requestedDir = $request->query('dir');

        $sortField = in_array($requestedSort, $allowedSort, true)
            ? $requestedSort
            : (in_array(($storedSort['field'] ?? null), $allowedSort, true) ? $storedSort['field'] : 'entry_date');

        $sortDir = in_array($requestedDir, ['asc', 'desc'], true)
            ? $requestedDir
            : (in_array(($storedSort['dir'] ?? null), ['asc', 'desc'], true) ? $storedSort['dir'] : 'asc');

        $request->session()->put($sortSessionKey, [
            'field' => $sortField,
            'dir' => $sortDir,
        ]);

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
        $officeFilter   = trim((string) ($filters['office'] ?? ''));
        $perPage     = (int) $request->query('per_page', 20);
        $perPage     = in_array($perPage, [10, 20, 50, 100, 0]) ? $perPage : 20;

        $dateFrom = trim($request->query('date_from', ''));
        $dateTo   = trim($request->query('date_to', ''));

        $with = ['referenceAccount', 'entryType', 'category.parent'];
        if ($officeEnabled) {
            $with[] = 'office';
        }

        $query = JournalEntry::query()
            ->where('budget_id', $budgetId)
            ->with($with)
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
            $query->where(function ($q) use ($categoryFilter) {
                $q->whereHas('category', fn ($c) => $c->whereNull('parent_id')->where('name', 'LIKE', '%' . $categoryFilter . '%'))
                    ->orWhereHas('category.parent', fn ($p) => $p->where('name', 'LIKE', '%' . $categoryFilter . '%'));
            });
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
        if ($officeEnabled && $officeFilter !== '') {
            $query->whereHas('office', fn ($o) => $o->where('name', 'LIKE', '%' . $officeFilter . '%'));
        }

        $entries = $query->paginate($perPage ?: 9999)->withQueryString();

        return view('journal.index', [
            'availableYears' => $availableYears,
            'selectedYear'   => $selectedYear,
            'yearRoute'      => route('journal.index'),
            'nextMovementNumber' => $this->nextMovementNumber('MOV'),
            'entries'        => $entries,
            'sortField'      => $sortField,
            'sortDir'        => $sortDir,
            'search'         => $search,
            'filters'        => $filters,
            'perPage'        => $perPage,
            'dateFrom'       => $dateFrom,
            'dateTo'         => $dateTo,
            'entryTypes'     => EntryType::where('budget_id', $budgetId)->orderBy('name')->get(),
            'categories'     => Category::where('budget_id', $budgetId)->orderBy('sort_order')->orderBy('name')->get(),
            'accounts'       => ReferenceAccount::where('budget_id', $budgetId)->orderBy('name')->get(),
            'offices'        => $officeEnabled ? Office::where('budget_id', $budgetId)->orderBy('name')->get() : collect(),
            'fieldVisibility' => $fieldVisibility,
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
        $data['movement_number'] = $this->nextMovementNumber('MOV');

        $entry = JournalEntry::create($data);
        $entry->load(['entryType', 'category.parent', 'referenceAccount']);

        if (Schema::hasTable('offices') && Schema::hasColumn('journal_entries', 'office_id')) {
            $entry->load('office');
        }

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

        if (Schema::hasTable('offices') && Schema::hasColumn('journal_entries', 'office_id')) {
            $entry->load('office');
        }

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

        $officeEnabled = Schema::hasTable('offices') && Schema::hasColumn('journal_entries', 'office_id');
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
            'reference_account_id' => $entry->reference_account_id ? (string) $entry->reference_account_id : '',
            'account_name'         => $entry->referenceAccount?->name ?? '',
            'account_edit_url'     => $entry->reference_account_id ? route('conti-riferimento.edit', $entry->reference_account_id) : null,
            'office_id'            => $officeEnabled && $entry->office_id ? (string) $entry->office_id : '',
            'office_name'          => $officeEnabled ? ($entry->office?->name ?? '') : '',
            'office_edit_url'      => $officeEnabled && $entry->office_id ? route('sedi.edit', $entry->office_id) : null,
        ];
    }

    private function formData(): array
    {
        $budgetId = $this->currentBudgetId();

        return [
            'nextMovementNumber' => $this->nextMovementNumber('MOV'),
            'entryTypes' => EntryType::where('budget_id', $budgetId)->orderBy('name')->get(),
            'categories' => Category::where('budget_id', $budgetId)->orderBy('sort_order')->orderBy('name')->get(),
            'accounts'   => ReferenceAccount::where('budget_id', $budgetId)->orderBy('name')->get(),
            'offices'    => (Schema::hasTable('offices') && Schema::hasColumn('journal_entries', 'office_id'))
                ? Office::where('budget_id', $budgetId)->orderBy('name')->get()
                : collect(),
            'fieldVisibility' => $this->journalFieldVisibility($budgetId),
        ];
    }

    private function rules(?int $ignoreId = null): array
    {
        $budgetId = $this->currentBudgetId();
        $fieldVisibility = $this->journalFieldVisibility($budgetId);
        $accountRules = [
            Rule::exists('reference_accounts', 'id')->where(fn ($q) => $q->where('budget_id', $budgetId)),
        ];

        if ($fieldVisibility['show_account']) {
            array_unshift($accountRules, 'required');
        } else {
            array_unshift($accountRules, 'nullable');
        }

        $officeEnabled = Schema::hasTable('offices') && Schema::hasColumn('journal_entries', 'office_id');
        return [
            'entry_date'           => ['required', 'date'],
            'entry_type_id'        => ['required', Rule::exists('entry_types', 'id')->where(fn ($q) => $q->where('budget_id', $budgetId))],
            'category_id'          => ['nullable', Rule::exists('categories', 'id')->where(fn ($q) => $q->where('budget_id', $budgetId))],
            'description'          => ['nullable', 'string', 'max:1000'],
            'amount'               => ['required', 'numeric', 'min:0.01'],
            'reference_account_id' => $accountRules,
            'office_id'            => $officeEnabled
                ? ['nullable', Rule::exists('offices', 'id')->where(fn ($q) => $q->where('budget_id', $budgetId))]
                : ['nullable'],
        ];
    }

    private function messages(): array
    {
        return [
            'entry_date.required'           => 'La data è obbligatoria.',
            'entry_type_id.required'        => 'Il tipo è obbligatorio.',
            'amount.required'               => "L'importo è obbligatorio.",
            'amount.min'                    => "L'importo deve essere maggiore di zero.",
            'reference_account_id.required' => 'Il conto di riferimento è obbligatorio.',
        ];
    }

    private function nextMovementNumber(string $prefix): string
    {
        $budgetId = $this->currentBudgetId();
        $max = 0;

        $numbers = JournalEntry::query()
            ->where('budget_id', $budgetId)
            ->where('movement_number', 'like', $prefix . '-%')
            ->pluck('movement_number');

        foreach ($numbers as $number) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/', (string) $number, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return sprintf('%s-%05d', $prefix, $max + 1);
    }
}
