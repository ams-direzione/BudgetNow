@extends('layouts.app')

@section('title', 'BudgetNow | Sedi')
@section('page-title', 'Sedi')

@section('content')

@php
$cols = [
    ['key' => 'name', 'label' => 'Nome Sede', 'sortable' => true, 'searchable' => true],
    ['key' => 'count', 'label' => 'Movimenti', 'sortable' => false],
];
@endphp

<x-data-table
    :columns="$cols"
    :rows="$offices"
    :sort-field="$sortField"
    :sort-dir="$sortDir"
    :filters="$filters"
    :per-page="$perPage"
    :create-url="route('sedi.create')"
    create-label="Aggiungi Sede"
    :colspan-empty="3"
>
    @foreach($offices as $office)
        @php
            $row = [
                'id' => $office->id,
                'name' => $office->name,
            ];
        @endphp

        <tbody x-data="editableRow(
                    {{ json_encode($row) }},
                    '{{ route('sedi.update', $office) }}',
                    '{{ route('sedi.destroy', $office) }}'
                )"
               class="border-b border-slate-100 last:border-0">

            <tr x-show="!editing" class="hover:bg-slate-50 align-middle">
                <td class="px-4 py-3 font-medium" x-text="orig.name"></td>
                <td class="px-4 py-3 text-slate-500">{{ $office->journalEntries()->count() }}</td>
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

            <tr x-show="editing" x-cloak class="bg-amber-50/60 align-top">
                <td class="px-4 py-3">
                    <input type="text" x-model="form.name" placeholder="Nome sede"
                           class="w-full rounded border px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           :class="errors.name ? 'border-rose-400 bg-rose-50' : 'border-slate-300'">
                    <p x-show="errors.name" x-text="errors.name?.[0]" class="text-rose-600 text-xs mt-0.5" x-cloak></p>
                </td>
                <td class="px-4 py-3 text-slate-400 text-xs">—</td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    <button @click="save()" :disabled="saving" title="Salva"
                            class="rounded-md bg-blue-600 p-1.5 text-white hover:bg-blue-700 disabled:opacity-50 transition-colors">
                        <svg x-show="!saving" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        <svg x-show="saving" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    </button>
                    <button @click="cancelEdit()" title="Annulla"
                            class="ml-1 rounded-md border border-slate-300 p-1.5 text-slate-600 hover:bg-slate-100 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                    </button>
                </td>
            </tr>

        </tbody>
    @endforeach
</x-data-table>

@endsection
