{{--
  Componente tabella riusabile.
  Props:
    $columns      — array di ['key' => string, 'label' => string, 'sortable' => bool]
    $rows         — paginatore Laravel (LengthAwarePaginator)
    $sortField    — campo corrente di ordinamento (string|null)
    $sortDir      — direzione corrente ('asc'|'desc')
    $search       — valore corrente della ricerca (string)
    $perPage      — righe per pagina (10|20|50|100|0=tutti)
    $createUrl    — URL per il pulsante "+ Aggiungi" (null = nessun pulsante)
    $createLabel  — testo del pulsante (default: 'Nuovo')
    $colspanEmpty — numero colonne per la riga vuota

  Slot $slot     — tbody delle righe esistenti
  Slot $newRow   — tbody della riga "Nuovo" (opzionale)
--}}
@props([
    'columns',
    'rows',
    'sortField'    => null,
    'sortDir'      => 'asc',
    'search'       => '',
    'filters'      => [],
    'perPage'      => 20,
    'createUrl'    => null,
    'createLabel'  => 'Nuovo',
    'colspanEmpty' => null,
])

@php
    $colspan = $colspanEmpty ?? (count($columns) + 1);
    $tableId = 'dt_' . md5(request()->path() . '|' . spl_object_id($rows));
    $queryWithoutFilters = request()->except(['filters', 'search', 'per_page', 'page', 'date_from', 'date_to']);
@endphp

<div>
    {{-- Barra azioni superiore --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">

        <div class="text-xs text-slate-500">
            Filtri per colonna disponibili in testa e a fondo tabella
        </div>

        <div class="flex items-center gap-2">
            {{-- Per page --}}
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

            {{-- Pulsante crea --}}
            @if($createUrl)
                <a href="{{ $createUrl }}"
                   class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors">
                    + {{ $createLabel }}
                </a>
            @endif
        </div>
    </div>

    <div class="rounded-xl bg-white shadow-sm">
        <form id="{{ $tableId }}_filters_top" method="GET" action="{{ request()->url() }}">
            @foreach($queryWithoutFilters as $k => $v)
                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
            @endforeach
            <input type="hidden" name="per_page" value="{{ $perPage }}">
            <input type="hidden" name="page" value="1">
        </form>
        <form id="{{ $tableId }}_filters_bottom" method="GET" action="{{ request()->url() }}">
            @foreach($queryWithoutFilters as $k => $v)
                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
            @endforeach
            <input type="hidden" name="per_page" value="{{ $perPage }}">
            <input type="hidden" name="page" value="1">
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm table-standard">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200">
                    <tr>
                        @foreach($columns as $col)
                            <th class="px-4 py-3 whitespace-nowrap">
                                @if($col['sortable'] ?? false)
                                    @php
                                        $isActive  = $sortField === $col['key'];
                                        $nextDir   = ($isActive && $sortDir === 'asc') ? 'desc' : 'asc';
                                        $sortUrl   = request()->fullUrlWithQuery(['sort' => $col['key'], 'dir' => $nextDir, 'page' => 1]);
                                    @endphp
                                    <a href="{{ $sortUrl }}"
                                       class="inline-flex items-center gap-1 hover:text-slate-800 transition-colors {{ $isActive ? 'text-slate-800 font-semibold' : '' }}">
                                        {{ $col['label'] }}
                                        <span class="text-slate-400">
                                            @if($isActive && $sortDir === 'asc') ↑
                                            @elseif($isActive && $sortDir === 'desc') ↓
                                            @else <span class="opacity-30">↕</span>
                                            @endif
                                        </span>
                                    </a>
                                @else
                                    {{ $col['label'] }}
                                @endif
                            </th>
                        @endforeach
                        <th class="px-4 py-3 text-right">Azioni</th>
                    </tr>
                    <tr class="border-t border-slate-200 bg-white normal-case">
                        @foreach($columns as $col)
                            <th class="px-4 py-2">
                                @if($col['searchable'] ?? false)
                                    <input type="text"
                                           name="filters[{{ $col['key'] }}]"
                                           value="{{ $filters[$col['key']] ?? '' }}"
                                           form="{{ $tableId }}_filters_top"
                                           placeholder="Cerca {{ strtolower($col['label']) }}…"
                                           class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-normal text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                @endif
                            </th>
                        @endforeach
                        <th class="px-4 py-2 text-right">
                            <div class="inline-flex items-center gap-1">
                                <button type="submit" form="{{ $tableId }}_filters_top"
                                        class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                                    Cerca
                                </button>
                                <a href="{{ request()->url() }}?{{ http_build_query($queryWithoutFilters) }}"
                                   class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-500 hover:bg-slate-50 transition-colors">
                                    Reset
                                </a>
                            </div>
                        </th>
                    </tr>
                </thead>

                {{-- Riga "Nuovo" (opzionale) --}}
                @isset($newRow)
                    {{ $newRow }}
                @endisset

                {{-- Righe dati --}}
                {{ $slot }}

                {{-- Riga vuota --}}
                @if($rows->isEmpty())
                    <tbody>
                        <tr>
                            <td colspan="{{ $colspan }}" class="px-4 py-10 text-center text-slate-500">
                                Nessun elemento presente.
                            </td>
                        </tr>
                    </tbody>
                @endif

                <tfoot class="bg-slate-50 text-left text-xs text-slate-500 border-t border-slate-200">
                    <tr class="normal-case">
                        @foreach($columns as $col)
                            <th class="px-4 py-2">
                                @if($col['searchable'] ?? false)
                                    <input type="text"
                                           name="filters[{{ $col['key'] }}]"
                                           value="{{ $filters[$col['key']] ?? '' }}"
                                           form="{{ $tableId }}_filters_bottom"
                                           placeholder="Cerca {{ strtolower($col['label']) }}…"
                                           class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-normal text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                @endif
                            </th>
                        @endforeach
                        <th class="px-4 py-2 text-right">
                            <div class="inline-flex items-center gap-1">
                                <button type="submit" form="{{ $tableId }}_filters_bottom"
                                        class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                                    Cerca
                                </button>
                                <a href="{{ request()->url() }}?{{ http_build_query($queryWithoutFilters) }}"
                                   class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-500 hover:bg-slate-50 transition-colors">
                                    Reset
                                </a>
                            </div>
                        </th>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if($rows->hasPages())
            <div class="border-t border-slate-200 px-4 py-4">
                {{ $rows->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
