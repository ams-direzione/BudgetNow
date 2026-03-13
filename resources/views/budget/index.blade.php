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
            <tbody class="border-b border-slate-100 last:border-0">
                <tr>
                    <td class="px-4 py-3 font-medium">{{ $budget->name }}</td>
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

                        <a href="{{ route('budget.edit', $budget) }}"
                           class="ml-1 rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 transition-colors">
                            Modifica
                        </a>

                        <button type="button"
                                class="ml-1 rounded-md border border-blue-300 px-3 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-50 transition-colors"
                                onclick="duplicateBudget({{ $budget->id }}, '{{ addslashes($budget->name) }}')">
                            Duplica
                        </button>

                        <form method="POST" action="{{ route('budget.destroy', $budget) }}" class="inline" onsubmit="return confirm('Eliminare questo budget e tutti i suoi dati?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="ml-1 rounded-md border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50 transition-colors">
                                Elimina
                            </button>
                        </form>
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
