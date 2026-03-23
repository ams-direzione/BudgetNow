@extends('layouts.app')

@section('title', 'BudgetNow | Libro Giornale')
@section('page-title', 'Libro Giornale')

{{-- ── Filtro date nell'intestazione ─────────────────────────────── --}}
@section('header-filters')
@php
    $italianMonths = ['Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno',
                      'Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];
    $selectedMonth = '';
    if ($dateFrom !== '' && $dateTo !== '') {
        if (preg_match('/^(\d{4})-(\d{2})-01$/', $dateFrom, $hm)) {
            $lastDay = date('t', mktime(0, 0, 0, (int)$hm[2], 1, (int)$hm[1]));
            if ($dateTo === "{$hm[1]}-{$hm[2]}-{$lastDay}") {
                $selectedMonth = "{$hm[1]}-{$hm[2]}";
            }
        }
    }
@endphp
@php
    $resetJournalFiltersUrl = route('journal.index', array_filter([
        'year' => $selectedYear,
        'sort' => $sortField ?? null,
        'dir' => $sortDir ?? null,
        'per_page' => $perPage ?? null,
    ], fn ($v) => $v !== null && $v !== ''));
@endphp
<form method="GET" action="{{ route('journal.index') }}"
      x-data="{
          dateFrom: '{{ $dateFrom }}',
          dateTo:   '{{ $dateTo }}',
          setMonth(val) {
              if (!val) { this.dateFrom = ''; this.dateTo = ''; return; }
              const [y, mo] = val.split('-');
              const last = new Date(+y, +mo, 0).getDate();
              this.dateFrom = y + '-' + mo + '-01';
              this.dateTo   = y + '-' + mo + '-' + String(last).padStart(2, '0');
              this.$nextTick(() => this.$el.submit());
          }
      }"
      class="flex flex-wrap items-center gap-2">
    <input type="hidden" name="year" value="{{ $selectedYear }}">

    {{-- Selezione rapida mese --}}
    <select @change="setMonth($event.target.value)"
            class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <option value="">— Mese —</option>
        @foreach($italianMonths as $i => $monthLabel)
            @php
                $mo  = str_pad($i + 1, 2, '0', STR_PAD_LEFT);
                $val = $selectedYear . '-' . $mo;
            @endphp
            <option value="{{ $val }}" @selected($selectedMonth === $val)>{{ $monthLabel }}</option>
        @endforeach
    </select>

    <span class="text-xs text-slate-400">o dal</span>

    <input type="date" name="date_from" x-model="dateFrom"
           class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">

    <span class="text-xs text-slate-400">al</span>

    <input type="date" name="date_to" x-model="dateTo"
           class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">

    <button type="submit"
            class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700 transition-colors">
        Filtra
    </button>

    <a href="{{ $resetJournalFiltersUrl }}"
       class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-500 hover:bg-slate-50 transition-colors">
        Reset filtri
    </a>
</form>
@endsection

@section('content')

{{-- Dati per Alpine.js (tipi, categorie, conti) --}}
@php
    $showAccount = (bool) ($fieldVisibility['show_account'] ?? true);
    $showOffice = (bool) ($fieldVisibility['show_office'] ?? false);
    $journalTypes = $entryTypes->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values();
    $journalCategories = $categories->map(fn ($c) => [
        'id' => $c->id,
        'name' => $c->name,
        'entry_type_id' => $c->entry_type_id,
        'parent_id' => $c->parent_id,
    ])->values();
    $journalAccounts = $accounts->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'code' => $a->account_code])->values();
    $journalOffices = $offices->map(fn ($o) => ['id' => $o->id, 'name' => $o->name])->values();
@endphp
<script>
window.journalData = {
    csrf: '{{ csrf_token() }}',
    next_movement_number: @json($nextMovementNumber ?? ''),
    types:      @json($journalTypes),
    categories: @json($journalCategories),
    accounts:   @json($journalAccounts),
    offices:    @json($journalOffices),
};

window.journalCategoryOptions = function(entryTypeId) {
    const all = (window.journalData.categories || [])
        .filter(c => String(c.entry_type_id) === String(entryTypeId || ''));
    const roots = all
        .filter(c => !c.parent_id)
        .sort((a, b) => String(a.name).localeCompare(String(b.name), 'it'));
    const out = [];
    roots.forEach((root) => {
        out.push({ id: String(root.id), label: String(root.name) });
        all
            .filter(c => String(c.parent_id) === String(root.id))
            .sort((a, b) => String(a.name).localeCompare(String(b.name), 'it'))
            .forEach((child) => out.push({ id: String(child.id), label: String(root.name) + ' - ' + String(child.name) }));
    });
    return out;
};

window.journalTypeLabelById = function(id) {
    const row = (window.journalData.types || []).find(t => String(t.id) === String(id || ''));
    return row ? String(row.name) : '';
};

window.journalTypeResolveId = function(label) {
    const needle = String(label || '').trim().toLowerCase();
    if (!needle) return '';
    const exact = (window.journalData.types || []).find(t => String(t.name).toLowerCase() === needle);
    return exact ? String(exact.id) : '';
};

window.journalCategoryResolveId = function(label, entryTypeId) {
    const needle = String(label || '').trim().toLowerCase();
    if (!needle) return '';
    const options = window.journalCategoryOptions(entryTypeId);
    const exact = options.find(o => o.label.toLowerCase() === needle);
    return exact ? String(exact.id) : '';
};

window.journalCategoryLabelById = function(id, entryTypeId) {
    if (!id) return '';
    const options = window.journalCategoryOptions(entryTypeId);
    const row = options.find(o => String(o.id) === String(id));
    return row ? row.label : '';
};

window.journalAccountOptions = function() {
    return (window.journalData.accounts || [])
        .map(a => ({
            id: String(a.id),
            label: String(a.name) + ' (' + String(a.code || '') + ')',
        }))
        .sort((a, b) => a.label.localeCompare(b.label, 'it'));
};

window.journalAccountResolveId = function(label) {
    const needle = String(label || '').trim().toLowerCase();
    if (!needle) return '';
    const exact = window.journalAccountOptions().find(o => o.label.toLowerCase() === needle);
    return exact ? String(exact.id) : '';
};

window.journalAccountLabelById = function(id) {
    if (!id) return '';
    const row = window.journalAccountOptions().find(o => String(o.id) === String(id));
    return row ? row.label : '';
};

window.journalOfficeOptions = function() {
    return (window.journalData.offices || [])
        .map(o => ({ id: String(o.id), label: String(o.name) }))
        .sort((a, b) => a.label.localeCompare(b.label, 'it'));
};

window.journalOfficeResolveId = function(label) {
    const needle = String(label || '').trim().toLowerCase();
    if (!needle) return '';
    const exact = window.journalOfficeOptions().find(o => o.label.toLowerCase() === needle);
    return exact ? String(exact.id) : '';
};

window.journalOfficeLabelById = function(id) {
    if (!id) return '';
    const row = window.journalOfficeOptions().find(o => String(o.id) === String(id));
    return row ? row.label : '';
};

/* ── Componente: riga nuova (journal-specific) ──────────── */
function newEntryRow() {
    return {
        open:   false,
        saving: false,
        errors: {},
        form:   {},

        openRow() {
            this.errors = {};
            this.form   = {
                movement_number:      window.journalData.next_movement_number || '',
                entry_date:           new Date().toISOString().split('T')[0],
                entry_type_id:        '',
                entry_type_label:     '',
                category_id:          '',
                category_label:       '',
                description:          '',
                amount:               '',
                reference_account_id: '',
                reference_account_label: '',
                office_id:            '',
                office_label:         '',
            };
            this.open = true;
            this.$nextTick(() => this.$el.querySelector('input')?.focus());
        },

        cancel() {
            this.open   = false;
            this.errors = {};
        },

        async save() {
            this.saving = true;
            this.errors = {};
            try {
                const resp = await fetch('/libro-giornale', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': window.journalData.csrf,
                    },
                    body: JSON.stringify(this.form),
                });
                const json = await resp.json();
                if (resp.ok) {
                    window.location.href = `/libro-giornale?year=${json.year}`;
                } else if (resp.status === 422) {
                    this.errors = json.errors || {};
                }
            } catch (err) { console.error(err); }
            this.saving = false;
        },
    };
}
</script>

@php
$cols = [
    ['key' => 'bulk_select',     'label' => 'Sel.',           'sortable' => false, 'searchable' => false],
    ['key' => 'movement_number', 'label' => 'N. Movimento',  'sortable' => true,  'searchable' => true],
    ['key' => 'entry_date',      'label' => 'Data',           'sortable' => true,  'searchable' => true],
    ['key' => 'entry_type',      'label' => 'Tipo',           'sortable' => false, 'searchable' => true],
    ['key' => 'category',        'label' => 'Categoria',      'sortable' => false, 'searchable' => true],
    ['key' => 'sub_category',    'label' => 'Sub Categoria',  'sortable' => false, 'searchable' => true],
    ['key' => 'description',     'label' => 'Descrizione',    'sortable' => false, 'searchable' => true],
    ['key' => 'amount',          'label' => 'Importo',        'sortable' => true,  'searchable' => true],
];
if ($showAccount) {
    $cols[] = ['key' => 'account', 'label' => 'Conto', 'sortable' => false, 'searchable' => true];
}
if ($showOffice) {
    $cols[] = ['key' => 'office', 'label' => 'Sede', 'sortable' => false, 'searchable' => true];
}
$journalColspan = count($cols) + 1;
@endphp

<div x-data="{
        newOpen: false,
        bulkDeleting: false,
        selectedCount: 0,
        selectionSelector: '.journal-row-selector',
        visibleCheckboxes() {
            return Array.from(document.querySelectorAll(this.selectionSelector));
        },
        selectedIds() {
            return this.visibleCheckboxes()
                .filter((box) => box.checked)
                .map((box) => String(box.value));
        },
        refreshSelectedCount() {
            this.selectedCount = this.selectedIds().length;
        },
        allVisibleSelected() {
            const boxes = this.visibleCheckboxes();
            return boxes.length > 0 && boxes.every((box) => box.checked);
        },
        toggleSelectAllVisible() {
            const boxes = this.visibleCheckboxes();
            if (boxes.length === 0) return;
            const targetChecked = !this.allVisibleSelected();
            boxes.forEach((box) => { box.checked = targetChecked; });
            this.refreshSelectedCount();
        },
        async deleteSelected() {
            const ids = this.selectedIds();
            if (ids.length === 0 || this.bulkDeleting) return;

            const label = ids.length === 1 ? 'questo movimento' : `questi ${ids.length} movimenti`;
            if (!confirm(`Eliminare ${label} visualizzati?`)) return;

            this.bulkDeleting = true;
            let failed = 0;
            try {
                for (const id of ids) {
                    const resp = await fetch(`/libro-giornale/${id}`, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': window.journalData.csrf,
                        },
                    });
                    if (!resp.ok) failed++;
                }
            } catch (err) {
                console.error(err);
                failed = ids.length;
            }

            this.bulkDeleting = false;
            if (failed > 0) {
                alert(`Eliminazione completata con errori: ${failed} elemento/i non eliminato/i.`);
            }
            window.location.reload();
        },
    }"
    x-init="refreshSelectedCount()"
    @journal-selection-changed.window="refreshSelectedCount()">

    <div class="mb-4 flex justify-end gap-2">
        <button type="button"
                @click="toggleSelectAllVisible()"
                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
            <span x-text="allVisibleSelected() ? 'Deseleziona tutti visualizzati' : 'Seleziona tutti visualizzati'"></span>
        </button>
        <button type="button"
                @click="deleteSelected()"
                :disabled="selectedCount === 0 || bulkDeleting"
                class="rounded-lg border border-rose-300 bg-rose-50 px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-100 disabled:cursor-not-allowed disabled:opacity-50 transition-colors">
            <span x-show="!bulkDeleting" x-text="selectedCount > 0 ? `Elimina selezionati (${selectedCount})` : 'Elimina selezionati'"></span>
            <span x-show="bulkDeleting" x-cloak>Eliminazione...</span>
        </button>
        <a href="{{ route('journal.import.csv.create') }}"
           class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
            Import CSV
        </a>
        <button @click="newOpen = true; $dispatch('open-new-row')"
                x-show="!newOpen"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors">
            + Nuovo Movimento
        </button>
    </div>

    <x-data-table
        :columns="$cols"
        :rows="$entries"
        :sort-field="$sortField"
        :sort-dir="$sortDir"
        :filters="$filters"
        :per-page="$perPage"
        :colspan-empty="$journalColspan"
    >
        {{-- ── Riga: nuovo movimento ────────────────────────── --}}
        <x-slot:newRow>
            <tbody x-data="newEntryRow()"
                   @open-new-row.window="openRow()"
                   @close-new-row.window="cancel(); $parent.newOpen = false">
                <tr x-show="open" x-cloak>
                    <td colspan="{{ $journalColspan }}" class="px-4 py-4 bg-blue-50/60 border-b border-blue-200">
                        @include('journal._inline_form')
                        <div class="flex items-center gap-2 mt-3">
                            <button @click="save()" :disabled="saving" title="Salva"
                                    class="rounded-lg bg-blue-600 p-2 text-white hover:bg-blue-700 disabled:opacity-50 transition-colors">
                                <svg x-show="!saving" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                <svg x-show="saving" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            </button>
                            <button @click="cancel(); $parent.newOpen = false" title="Annulla"
                                    class="rounded-lg border border-slate-300 p-2 text-slate-600 hover:bg-slate-100 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </x-slot:newRow>

        {{-- ── Righe: movimenti esistenti ──────────────────── --}}
        @foreach($entries as $entry)
            @php
                $cat    = $entry->category;
                $parent = $cat?->parent;
                $baseQuery = request()->query();
                unset($baseQuery['page'], $baseQuery['date_from'], $baseQuery['date_to']);
                $baseFilters = $filters ?? [];
                $dateFilterUrl = route('journal.index', array_merge($baseQuery, [
                    'year' => $selectedYear,
                    'page' => 1,
                    'filters' => array_merge($baseFilters, [
                        'entry_date' => $entry->entry_date->format('Y-m-d'),
                    ]),
                ]));
                $categoryFilterValue = $parent ? $parent->name : ($cat?->name ?? '');
                $categoryFilterUrl = $categoryFilterValue !== '' ? route('journal.index', array_merge($baseQuery, [
                    'year' => $selectedYear,
                    'page' => 1,
                    'filters' => array_merge($baseFilters, [
                        'category' => $categoryFilterValue,
                    ]),
                ])) : null;
                $subCategoryFilterValue = $parent ? $cat->name : '';
                $subCategoryFilterUrl = $subCategoryFilterValue !== '' ? route('journal.index', array_merge($baseQuery, [
                    'year' => $selectedYear,
                    'page' => 1,
                    'filters' => array_merge($baseFilters, [
                        'sub_category' => $subCategoryFilterValue,
                    ]),
                ])) : null;
                $accountFilterValue = $entry->referenceAccount?->name ?? '';
                $accountFilterUrl = $accountFilterValue !== '' ? route('journal.index', array_merge($baseQuery, [
                    'year' => $selectedYear,
                    'page' => 1,
                    'filters' => array_merge($baseFilters, [
                        'account' => $accountFilterValue,
                    ]),
                ])) : null;
                $officeFilterValue = $showOffice ? ($entry->office?->name ?? '') : '';
                $officeFilterUrl = ($showOffice && $officeFilterValue !== '') ? route('journal.index', array_merge($baseQuery, [
                    'year' => $selectedYear,
                    'page' => 1,
                    'filters' => array_merge($baseFilters, [
                        'office' => $officeFilterValue,
                    ]),
                ])) : null;
                $entryTypeFilterValue = $entry->entryType?->name ?? '';
                $entryTypeFilterUrl = $entryTypeFilterValue !== '' ? route('journal.index', array_merge($baseQuery, [
                    'year' => $selectedYear,
                    'page' => 1,
                    'filters' => array_merge($baseFilters, [
                        'entry_type' => $entryTypeFilterValue,
                    ]),
                ])) : null;
                $rowData = [
                    'id'                   => $entry->id,
                    'movement_number'      => $entry->movement_number,
                    'entry_date'           => $entry->entry_date->format('Y-m-d'),
                    'entry_date_formatted' => $entry->entry_date->format('d/m/Y'),
                    'entry_type_id'        => (string) $entry->entry_type_id,
                    'entry_type_name'      => $entry->entryType?->name ?? '',
                    'category_id'          => $entry->category_id ? (string) $entry->category_id : '',
                    'category_name'        => $parent ? $parent->name : ($cat?->name ?? ''),
                    'sub_category_name'    => $parent ? $cat->name : '',
                    'description'          => $entry->description ?? '',
                    'amount'               => (float) $entry->amount,
                    'amount_formatted'     => number_format((float) $entry->amount, 2, ',', '.'),
                    'reference_account_id' => $entry->reference_account_id ? (string) $entry->reference_account_id : '',
                    'account_name'         => $entry->referenceAccount?->name ?? '',
                    'account_edit_url'     => $entry->reference_account_id ? route('conti-riferimento.edit', $entry->reference_account_id) : null,
                    'office_id'            => $entry->office_id ? (string) $entry->office_id : '',
                    'office_name'          => $showOffice ? ($entry->office?->name ?? '') : '',
                    'office_edit_url'      => ($showOffice && $entry->office_id) ? route('sedi.edit', $entry->office_id) : null,
                ];
            @endphp
            <tbody x-data="editableRow(
                        {{ json_encode($rowData) }},
                        '/libro-giornale/{{ $entry->id }}',
                        '/libro-giornale/{{ $entry->id }}'
                    )"
                   class="border-b border-slate-100 last:border-0">

                {{-- Riga vista --}}
                <tr x-show="!editing" class="hover:bg-slate-50 align-middle">
                    <td class="px-4 py-3">
                        <input type="checkbox"
                               class="journal-row-selector h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                               value="{{ $entry->id }}"
                               @change="$dispatch('journal-selection-changed')"
                               aria-label="Seleziona movimento {{ $entry->movement_number }}">
                    </td>
                    <td class="px-4 py-3 font-medium">
                        <div class="flex items-center justify-between gap-2">
                            <span x-text="orig.movement_number"></span>
                            <a href="{{ route('journal.edit', $entry) }}"
                               title="Apri scheda modifica"
                               class="rounded-md border border-slate-300 p-1.5 text-slate-600 hover:bg-slate-100 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/></svg>
                            </a>
                        </div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <a href="{{ $dateFilterUrl }}" class="text-blue-700 hover:underline" x-text="orig.entry_date_formatted"></a>
                    </td>
                    <td class="px-4 py-3">
                        @if($entryTypeFilterUrl)
                            <a href="{{ $entryTypeFilterUrl }}" class="inline-block">
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium"
                                      :class="orig.entry_type_name === 'Entrata' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
                                      x-text="orig.entry_type_name || '—'"></span>
                            </a>
                        @else
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium"
                                  :class="orig.entry_type_name === 'Entrata' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
                                  x-text="orig.entry_type_name || '—'"></span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-600">
                        @if($categoryFilterUrl)
                            <a href="{{ $categoryFilterUrl }}" class="text-blue-700 hover:underline" x-text="orig.category_name || '—'"></a>
                        @else
                            <span x-text="orig.category_name || '—'"></span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-500">
                        @if($subCategoryFilterUrl)
                            <a href="{{ $subCategoryFilterUrl }}" class="text-blue-700 hover:underline" x-text="orig.sub_category_name || '—'"></a>
                        @else
                            <span x-text="orig.sub_category_name || '—'"></span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-500 max-w-[12rem] truncate"
                        :title="orig.description || ''"
                        x-text="orig.description || '—'"></td>
                    <td class="px-4 py-3 font-semibold whitespace-nowrap"
                        :class="orig.entry_type_name === 'Entrata' ? 'text-emerald-600' : 'text-rose-600'"
                        x-text="(orig.entry_type_name === 'Entrata' ? '+ ' : '- ') + '€ ' + orig.amount_formatted">
                    </td>
                    @if($showAccount)
                        <td class="px-4 py-3">
                            @if($accountFilterUrl)
                                <a href="{{ $accountFilterUrl }}"
                                   class="text-blue-600 hover:underline text-sm"
                                   x-text="orig.account_name || '—'"></a>
                            @else
                                <span class="text-sm text-slate-500" x-text="orig.account_name || '—'"></span>
                            @endif
                        </td>
                    @endif
                    @if($showOffice)
                        <td class="px-4 py-3">
                            @if($officeFilterUrl)
                                <a href="{{ $officeFilterUrl }}"
                                   class="text-blue-600 hover:underline text-sm"
                                   x-text="orig.office_name || '—'"></a>
                            @else
                                <span class="text-sm text-slate-500" x-text="orig.office_name || '—'"></span>
                            @endif
                        </td>
                    @endif
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <button @click="startEdit()" title="Modifica"
                                class="rounded-md border border-slate-300 p-1.5 text-slate-600 hover:bg-slate-100 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/></svg>
                        </button>
                        <button @click="del()" title="Elimina"
                                class="ml-1 rounded-md border border-rose-300 p-1.5 text-rose-600 hover:bg-rose-50 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        </button>
                    </td>
                </tr>

                {{-- Riga modifica --}}
                <tr x-show="editing" x-cloak class="align-top">
                    <td colspan="{{ $journalColspan }}" class="px-4 py-4 bg-amber-50/60 border-t border-amber-200">
                        @include('journal._inline_form')
                        <div class="flex items-center gap-2 mt-3">
                            <button @click="save()" :disabled="saving" title="Aggiorna"
                                    class="rounded-lg bg-blue-600 p-2 text-white hover:bg-blue-700 disabled:opacity-50 transition-colors">
                                <svg x-show="!saving" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                <svg x-show="saving" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            </button>
                            <button @click="cancelEdit()" title="Annulla"
                                    class="rounded-lg border border-slate-300 p-2 text-slate-600 hover:bg-slate-100 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>

            </tbody>
        @endforeach

    </x-data-table>

</div>

@endsection
