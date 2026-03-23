@extends('layouts.app')

@section('title', 'BudgetNow | Budget')
@section('page-title', 'Budget')

@section('content')
    @php
        $cols = [
            ['key' => 'name', 'label' => 'Nome', 'sortable' => true, 'searchable' => true],
            ['key' => 'status', 'label' => 'Stato', 'sortable' => false],
        ];
    @endphp

    <x-data-table
        :columns="$cols"
        :rows="$budgets"
        :sort-field="$sortField"
        :sort-dir="$sortDir"
        :filters="$filters"
        :per-page="$perPage"
        :create-url="route('budget.create')"
        create-label="Nuovo Budget"
        :colspan-empty="3"
    >
        @foreach($budgets as $budget)
            @php $row = ['id' => $budget->id, 'name' => $budget->name]; @endphp
            <tbody x-data="editableRow(
                        {{ json_encode($row) }},
                        '{{ route('budget.update', $budget) }}',
                        '{{ route('budget.destroy', $budget) }}'
                    )"
                   class="border-b border-slate-100 last:border-0">
                <tr x-show="!editing">
                    <td class="px-4 py-3 font-medium" x-text="orig.name"></td>
                    <td class="px-4 py-3">
                        @if((int) $activeBudgetId === (int) $budget->id)
                            <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700">Attivo</span>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        @if((int) $activeBudgetId !== (int) $budget->id)
                            <form method="POST" action="{{ route('budget.switch', $budget) }}" class="inline">
                                @csrf
                                <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 transition-colors">
                                    Imposta attivo
                                </button>
                            </form>
                        @endif

                        <button type="button"
                                @click="startEdit()"
                                class="ml-1 rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 transition-colors">
                            Modifica
                        </button>

                        <button type="button"
                                class="ml-1 rounded-md border border-blue-300 px-3 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-50 transition-colors"
                                onclick="duplicateBudget({{ $budget->id }}, '{{ addslashes($budget->name) }}')">
                            Duplica
                        </button>

                        <button type="button"
                                @click="del()"
                                class="ml-1 rounded-md border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50 transition-colors">
                            Elimina
                        </button>
                    </td>
                </tr>

                <tr x-show="editing" x-cloak class="bg-amber-50/60 align-top">
                    <td class="px-4 py-3">
                        <input type="text" x-model="form.name" placeholder="Nome budget"
                               class="w-full rounded border px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                               :class="errors.name ? 'border-rose-400 bg-rose-50' : 'border-slate-300'">
                        <p x-show="errors.name" x-text="errors.name?.[0]" class="text-rose-600 text-xs mt-0.5" x-cloak></p>
                    </td>
                    <td class="px-4 py-3 text-slate-400 text-xs">—</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <button @click="save()" :disabled="saving"
                                class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-50 transition-colors">
                            <span x-show="!saving">Salva</span>
                            <span x-show="saving" x-cloak>…</span>
                        </button>
                        <button @click="cancelEdit()"
                                class="ml-1 rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-100 transition-colors">
                            Annulla
                        </button>
                    </td>
                </tr>
            </tbody>
        @endforeach
    </x-data-table>

    <form id="duplicateBudgetForm" method="POST" action="" class="hidden">
        @csrf
        <input type="hidden" name="name" id="duplicateName">
        <input type="hidden" name="mode" id="duplicateMode">
    </form>

    <script>
        function duplicateBudget(id, currentName) {
            var name = prompt('Nome del nuovo budget:', currentName + ' (Copia)');
            if (!name) return;

            var copyCategories = confirm('Vuoi copiare anche le categorie dal budget corrente?');

            var form = document.getElementById('duplicateBudgetForm');
            document.getElementById('duplicateName').value = name;
            document.getElementById('duplicateMode').value = copyCategories ? 'categories' : 'empty';
            form.action = '/budget/' + id + '/duplicate';
            form.submit();
        }
    </script>
@endsection
