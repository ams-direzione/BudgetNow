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

    @if($dateFrom !== '' || $dateTo !== '')
        <a href="{{ route('journal.index', ['year' => $selectedYear]) }}"
           class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-500 hover:bg-slate-50 transition-colors">
            ✕ Reset
        </a>
    @endif
</form>
@endsection

@section('content')

{{-- Dati per Alpine.js (tipi, categorie, conti) --}}
<script>
window.journalData = {
    csrf: '{{ csrf_token() }}',
    types:      @json($entryTypes->map(fn($t) => ['id' => $t->id, 'name' => $t->name])),
    categories: @json($categories->map(fn($c) => ['id' => $c->id, 'name' => $c->name])),
    accounts:   @json($accounts->map(fn($a) => ['id' => $a->id, 'name' => $a->name, 'code' => $a->account_code])),
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
                movement_number:      '',
                entry_date:           new Date().toISOString().split('T')[0],
                entry_type_id:        '',
                category_id:          '',
                description:          '',
                amount:               '',
                reference_account_id: '',
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
    ['key' => 'movement_number', 'label' => 'N. Movimento',  'sortable' => true,  'searchable' => true],
    ['key' => 'entry_date',      'label' => 'Data',           'sortable' => true,  'searchable' => true],
    ['key' => 'entry_type',      'label' => 'Tipo',           'sortable' => false, 'searchable' => true],
    ['key' => 'category',        'label' => 'Categoria',      'sortable' => false, 'searchable' => true],
    ['key' => 'sub_category',    'label' => 'Sub Categoria',  'sortable' => false, 'searchable' => true],
    ['key' => 'description',     'label' => 'Descrizione',    'sortable' => false, 'searchable' => true],
    ['key' => 'amount',          'label' => 'Importo',        'sortable' => true,  'searchable' => true],
    ['key' => 'account',         'label' => 'Conto',          'sortable' => false, 'searchable' => true],
];
@endphp

<div x-data="{ newOpen: false }">

    <div class="mb-4 flex justify-end">
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
        :colspan-empty="9"
    >
        {{-- ── Riga: nuovo movimento ────────────────────────── --}}
        <x-slot:newRow>
            <tbody x-data="newEntryRow()"
                   @open-new-row.window="openRow()"
                   @close-new-row.window="cancel(); $parent.newOpen = false">
                <tr x-show="open" x-cloak>
                    <td colspan="9" class="px-4 py-4 bg-blue-50/60 border-b border-blue-200">
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
                    'reference_account_id' => (string) $entry->reference_account_id,
                    'account_name'         => $entry->referenceAccount?->name ?? '',
                    'account_edit_url'     => route('conti-riferimento.edit', $entry->reference_account_id),
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
                    <td class="px-4 py-3 font-medium" x-text="orig.movement_number"></td>
                    <td class="px-4 py-3 whitespace-nowrap" x-text="orig.entry_date_formatted"></td>
                    <td class="px-4 py-3">
                        <span class="rounded-full px-2.5 py-1 text-xs font-medium"
                              :class="orig.entry_type_name === 'Entrata' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
                              x-text="orig.entry_type_name || '—'"></span>
                    </td>
                    <td class="px-4 py-3 text-slate-600" x-text="orig.category_name || '—'"></td>
                    <td class="px-4 py-3 text-slate-500" x-text="orig.sub_category_name || '—'"></td>
                    <td class="px-4 py-3 text-slate-500 max-w-[12rem] truncate" x-text="orig.description || '—'"></td>
                    <td class="px-4 py-3 font-semibold whitespace-nowrap"
                        :class="orig.entry_type_name === 'Entrata' ? 'text-emerald-600' : 'text-rose-600'"
                        x-text="(orig.entry_type_name === 'Entrata' ? '+ ' : '- ') + '€ ' + orig.amount_formatted">
                    </td>
                    <td class="px-4 py-3">
                        <a :href="orig.account_edit_url"
                           class="text-blue-600 hover:underline text-sm"
                           x-text="orig.account_name || '—'"></a>
                    </td>
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
                    <td colspan="9" class="px-4 py-4 bg-amber-50/60 border-t border-amber-200">
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
