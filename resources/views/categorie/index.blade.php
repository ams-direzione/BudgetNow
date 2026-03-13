@extends('layouts.app')

@section('title', 'BudgetNow | Categorie ' . $tipo->name)
@section('page-title', 'Categorie — ' . $tipo->name)

@section('content')

{{-- Dati per Alpine.js: select genitori (solo radici) --}}
<script>
window.parentCategories = @json($parents->map(fn($p) => ['id' => (string) $p->id, 'name' => $p->name]));
</script>

{{-- Barra azioni --}}
<div class="mb-4 flex items-center justify-between">
    <div class="flex items-center gap-3">
        <span class="text-sm text-slate-500">
            {{ $rootCategories->total() }} radici,
            {{ $rootCategories->sum(fn($r) => $r->children->count()) }} sottocategorie
        </span>
        {{-- Sort toggle --}}
        @php
            $nextDir  = $sortDir === 'asc' ? 'desc' : 'asc';
            $sortUrl  = request()->fullUrlWithQuery(['dir' => $nextDir, 'page' => 1]);
        @endphp
        <a href="{{ $sortUrl }}"
           class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-100 transition-colors">
            Nome {{ $sortDir === 'asc' ? '↑ A→Z' : '↓ Z→A' }}
        </a>
    </div>
    <a href="{{ route('categorie.create', ['tipo' => $tipo->id]) }}"
       class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors">
        + Aggiungi Categoria
    </a>
</div>

<div class="mb-4 flex items-center justify-between gap-2">
    <form method="GET" action="{{ request()->url() }}" class="flex items-center gap-2">
        @foreach(request()->except(['filters', 'search', 'page']) as $k => $v)
            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
        @endforeach
        <input type="text" name="filters[category]" value="{{ $filters['category'] ?? '' }}"
               placeholder="Cerca categoria…"
               class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-64">
        <button type="submit"
                class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
            Cerca
        </button>
    </form>

    <form method="GET" action="{{ request()->url() }}">
        @foreach(request()->except(['per_page', 'page']) as $k => $v)
            @if($k === 'filters' && is_array($v))
                @foreach($v as $fk => $fv)
                    <input type="hidden" name="filters[{{ $fk }}]" value="{{ $fv }}">
                @endforeach
            @else
                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
            @endif
        @endforeach
        <select name="per_page"
                onchange="this.form.submit()"
                class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            @foreach([10 => '10 righe', 20 => '20 righe', 50 => '50 righe', 100 => '100 righe', 0 => 'Tutti'] as $val => $label)
                <option value="{{ $val }}" @selected((int)$val === (int)$perPage)>{{ $label }}</option>
            @endforeach
        </select>
    </form>
</div>

<div class="rounded-xl bg-white shadow-sm overflow-hidden">
    <table class="min-w-full text-sm table-standard">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200">
            <tr>
                <th class="px-4 py-3">Categoria</th>
                <th class="px-4 py-3 w-28">Movimenti</th>
                <th class="px-4 py-3 text-right w-36">Azioni</th>
            </tr>
        </thead>

        @forelse($rootCategories as $parent)

            {{-- ══ RIGA PADRE ══════════════════════════════════════════════ --}}
            @php
                $parentRow = [
                    'id'            => $parent->id,
                    'name'          => $parent->name,
                    'entry_type_id' => (string) $parent->entry_type_id,
                    'parent_id'     => '',
                    'parent_name'   => '',
                    'is_root'       => true,
                ];
            @endphp
            <tbody x-data="editableRow(
                        {{ json_encode($parentRow) }},
                        '{{ route('categorie.update', $parent) }}',
                        '{{ route('categorie.destroy', $parent) }}'
                    )"
                   class="border-t-2 border-slate-200">

                {{-- Vista padre --}}
                <tr x-show="!editing" class="bg-slate-50 align-middle hover:bg-slate-100 transition-colors">
                    <td class="px-4 py-2.5">
                        <div class="flex items-center gap-2">
                            <span class="text-slate-400 text-xs select-none">▸</span>
                            <span class="font-semibold text-slate-800" x-text="orig.name"></span>
                            @if($parent->children->count() > 0)
                                <span class="rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-medium text-slate-500">
                                    {{ $parent->children->count() }}
                                </span>
                            @endif
                        </div>
                    </td>
                    <td class="px-4 py-2.5 text-slate-500 text-xs">
                        @if($parent->journal_entries_count > 0)
                            {{ $parent->journal_entries_count }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-2.5 text-right whitespace-nowrap">
                        <button @click="startEdit()" title="Modifica"
                                class="rounded-md border border-slate-300 p-1.5 text-slate-600 hover:bg-white transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/></svg>
                        </button>
                        <button @click="del()" title="Elimina"
                                class="ml-1 rounded-md border border-rose-300 p-1.5 text-rose-600 hover:bg-rose-50 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        </button>
                    </td>
                </tr>

                {{-- Modifica inline padre --}}
                <tr x-show="editing" x-cloak class="bg-amber-50/60 align-top">
                    <td class="px-4 py-2.5">
                        <input type="text" x-model="form.name" placeholder="Nome categoria padre"
                               class="w-full rounded border px-2 py-1.5 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-blue-500"
                               :class="errors.name ? 'border-rose-400 bg-rose-50' : 'border-slate-300'">
                        <p x-show="errors.name" x-text="errors.name?.[0]" class="text-rose-600 text-xs mt-0.5" x-cloak></p>
                    </td>
                    <td class="px-4 py-2.5 text-slate-400 text-xs">—</td>
                    <td class="px-4 py-2.5 text-right whitespace-nowrap">
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

            {{-- ── RIGHE FIGLIE ──────────────────────────────────────────── --}}
            @foreach($parent->children->sortBy('name') as $child)
                @php
                    $childRow = [
                        'id'            => $child->id,
                        'name'          => $child->name,
                        'entry_type_id' => (string) $child->entry_type_id,
                        'parent_id'     => (string) $child->parent_id,
                        'parent_name'   => $parent->name,
                        'is_root'       => false,
                    ];
                @endphp
                <tbody x-data="editableRow(
                            {{ json_encode($childRow) }},
                            '{{ route('categorie.update', $child) }}',
                            '{{ route('categorie.destroy', $child) }}'
                        )"
                       class="border-t border-slate-100">

                    {{-- Vista figlia --}}
                    <tr x-show="!editing" class="bg-white align-middle hover:bg-slate-50 transition-colors">
                        <td class="py-2.5 pr-4">
                            <div class="flex items-center">
                                {{-- connettore visivo albero --}}
                                <span class="w-10 shrink-0 flex items-center justify-end pr-2 text-slate-300 text-sm select-none">└</span>
                                <span class="text-slate-700" x-text="orig.name"></span>
                            </div>
                        </td>
                        <td class="px-4 py-2.5 text-slate-500 text-xs">
                            {{ $child->journal_entries_count > 0 ? $child->journal_entries_count : '—' }}
                        </td>
                        <td class="px-4 py-2.5 text-right whitespace-nowrap">
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

                    {{-- Modifica inline figlia --}}
                    <tr x-show="editing" x-cloak class="bg-amber-50/60 align-top">
                        <td class="py-2.5 pr-4">
                            <div class="flex items-center gap-2">
                                <span class="w-10 shrink-0 text-center text-slate-300 text-sm select-none">└</span>
                                <div class="flex-1">
                                    <input type="text" x-model="form.name" placeholder="Nome sottocategoria"
                                           class="w-full rounded border px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           :class="errors.name ? 'border-rose-400 bg-rose-50' : 'border-slate-300'">
                                    <p x-show="errors.name" x-text="errors.name?.[0]" class="text-rose-600 text-xs mt-0.5" x-cloak></p>
                                </div>
                                <div class="shrink-0 w-40">
                                    <select x-model="form.parent_id"
                                            class="w-full rounded border border-slate-300 px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="">— Nessuna —</option>
                                        <template x-for="p in window.parentCategories" :key="p.id">
                                            <option :value="p.id" x-text="p.name" :selected="p.id === form.parent_id"></option>
                                        </template>
                                    </select>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-2.5 text-slate-400 text-xs">—</td>
                        <td class="px-4 py-2.5 text-right whitespace-nowrap">
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

        @empty
            <tbody>
                <tr>
                    <td colspan="3" class="px-4 py-10 text-center text-slate-500">
                        Nessuna categoria per <strong>{{ $tipo->name }}</strong>.
                        <a href="{{ route('categorie.create', ['tipo' => $tipo->id]) }}"
                           class="text-blue-600 hover:underline">Aggiungi la prima.</a>
                    </td>
                </tr>
            </tbody>
        @endforelse

        <tfoot class="bg-slate-50 border-t border-slate-200">
            <tr>
                <td class="px-4 py-2" colspan="2">
                    <form method="GET" action="{{ request()->url() }}" class="flex items-center gap-2">
                        @foreach(request()->except(['filters', 'search', 'page']) as $k => $v)
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endforeach
                        <input type="text" name="filters[category]" value="{{ $filters['category'] ?? '' }}"
                               placeholder="Cerca categoria…"
                               class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 w-56">
                        <button type="submit"
                                class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                            Cerca
                        </button>
                    </form>
                </td>
                <td class="px-4 py-2 text-right text-xs text-slate-500">Ricerca rapida</td>
            </tr>
        </tfoot>
    </table>
</div>

@if($rootCategories->hasPages())
    <div class="border-t border-slate-200 px-4 py-4 bg-white rounded-b-xl">
        {{ $rootCategories->withQueryString()->links() }}
    </div>
@endif

@endsection
