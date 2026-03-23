@extends('layouts.app')

@section('title', 'BudgetNow | Corrente')
@section('page-title', 'Corrente')

@section('content')
    @php
        $categoryPanels = collect($categories ?? [])->map(function ($category) {
            return [
                'key' => 'cat-' . $category['id'],
                'payload' => [
                    'kind' => 'Categoria',
                    'name' => $category['name'],
                    'category_name' => $category['name'],
                    'months' => $category['months'],
                    'months_previous' => $category['months_previous'],
                    'months_two_years_ago' => $category['months_two_years_ago'],
                    'sub_breakdown' => collect($category['sub_rows'] ?? [])->filter(fn ($sub) => ($sub['name'] ?? '—') !== '—')->map(fn ($sub) => [
                        'name' => $sub['name'],
                        'total' => $sub['subtotal'],
                    ])->values(),
                    'total' => $category['total'],
                ],
            ];
        })->values();
        $expandableCategoryKeys = collect($categories ?? [])->filter(function ($category) {
            return collect($category['sub_rows'] ?? [])->contains(fn ($sub) => ($sub['name'] ?? '—') !== '—');
        })->map(fn ($category) => 'cat-' . $category['id'])->values();
        $initialExpandedCategoryKeys = $search !== '' ? $expandableCategoryKeys : collect();
        $selectedYear = $selectedYear ?? (int) now()->format('Y');
    @endphp
    <div x-data="correnteDashboard()">
        <div class="mb-4 flex items-center gap-2">
            <a href="{{ route('corrente.entrate', ['year' => $selectedYear, 'search' => $search, 'sort' => $sortField ?? 'category', 'dir' => $sortDir ?? 'asc']) }}"
               class="rounded-lg px-4 py-2 text-sm font-medium transition-colors {{ $activeTab === 'entrate' ? 'bg-blue-600 text-white' : 'border border-slate-300 text-slate-700 hover:bg-slate-50' }}">
                Entrate
            </a>
            <a href="{{ route('corrente.uscite', ['year' => $selectedYear, 'search' => $search, 'sort' => $sortField ?? 'category', 'dir' => $sortDir ?? 'asc']) }}"
               class="rounded-lg px-4 py-2 text-sm font-medium transition-colors {{ $activeTab === 'uscite' ? 'bg-blue-600 text-white' : 'border border-slate-300 text-slate-700 hover:bg-slate-50' }}">
                Uscite
            </a>
        </div>

        <div class="mb-4">
            <form method="GET" action="{{ $activeTab === 'entrate' ? route('corrente.entrate') : route('corrente.uscite') }}" class="flex items-center gap-2">
                <input type="hidden" name="year" value="{{ $selectedYear }}">
                <input type="hidden" name="sort" value="{{ $sortField ?? 'category' }}">
                <input type="hidden" name="dir" value="{{ $sortDir ?? 'asc' }}">
                <input type="text"
                       name="search"
                       value="{{ $search }}"
                       placeholder="Cerca categoria o sub-categoria…"
                       class="w-full max-w-md rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <button type="submit"
                        class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                    Cerca
                </button>
                @if($search !== '')
                    <a href="{{ $activeTab === 'entrate' ? route('corrente.entrate', ['year' => $selectedYear, 'sort' => $sortField ?? 'category', 'dir' => $sortDir ?? 'asc']) : route('corrente.uscite', ['year' => $selectedYear, 'sort' => $sortField ?? 'category', 'dir' => $sortDir ?? 'asc']) }}"
                       class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-500 hover:bg-slate-50 transition-colors">
                        Reset
                    </a>
                @endif
            </form>
        </div>

        <div class="rounded-xl bg-white shadow-sm overflow-hidden">
            <table class="min-w-full text-sm table-standard">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200">
                    <tr>
                        <th colspan="3" class="px-4 py-2">
                            <div class="flex items-center justify-end gap-2 normal-case tracking-normal">
                                <button type="button"
                                        @click="expandAllSubcategories()"
                                        class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M10 2a.75.75 0 01.75.75V9h6.25a.75.75 0 010 1.5h-6.25v6.25a.75.75 0 01-1.5 0V10.5H3a.75.75 0 010-1.5h6.25V2.75A.75.75 0 0110 2z" clip-rule="evenodd" />
                                    </svg>
                                    Espandi tutto
                                </button>
                                <button type="button"
                                        @click="collapseAllSubcategories()"
                                        class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h12.5a.75.75 0 010 1.5H3.75A.75.75 0 013 10z" clip-rule="evenodd" />
                                    </svg>
                                    Comprimi tutto
                                </button>
                            </div>
                        </th>
                    </tr>
                    <tr>
                        @php
                            $isCategorySort = ($sortField ?? 'category') === 'category';
                            $isSubcategorySort = ($sortField ?? 'category') === 'sub_category';
                            $isSubtotalSort = ($sortField ?? 'category') === 'subtotal';
                            $categoryNextDir = $isCategorySort && ($sortDir ?? 'asc') === 'asc' ? 'desc' : 'asc';
                            $subcategoryNextDir = $isSubcategorySort && ($sortDir ?? 'asc') === 'asc' ? 'desc' : 'asc';
                            $subtotalNextDir = $isSubtotalSort && ($sortDir ?? 'asc') === 'asc' ? 'desc' : 'asc';
                        @endphp
                        <th class="px-4 py-3">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'category', 'dir' => $categoryNextDir]) }}" class="inline-flex items-center gap-1 hover:text-slate-700">
                                Categoria
                                <span class="text-[10px]">{{ $isCategorySort ? (($sortDir ?? 'asc') === 'asc' ? '↑' : '↓') : '↕' }}</span>
                            </a>
                        </th>
                        <th class="px-4 py-3">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'sub_category', 'dir' => $subcategoryNextDir]) }}" class="inline-flex items-center gap-1 hover:text-slate-700">
                                Sub-categoria
                                <span class="text-[10px]">{{ $isSubcategorySort ? (($sortDir ?? 'asc') === 'asc' ? '↑' : '↓') : '↕' }}</span>
                            </a>
                        </th>
                        <th class="px-4 py-3 text-right">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'subtotal', 'dir' => $subtotalNextDir]) }}" class="inline-flex items-center gap-1 hover:text-slate-700">
                                Subtotale
                                <span class="text-[10px]">{{ $isSubtotalSort ? (($sortDir ?? 'asc') === 'asc' ? '↑' : '↓') : '↕' }}</span>
                            </a>
                        </th>
                    </tr>
                </thead>

                @forelse($categories as $category)
                    @php
                        $categoryKey = 'cat-' . $category['id'];
                        $categoryPayload = [
                            'kind' => 'Categoria',
                            'name' => $category['name'],
                            'category_name' => $category['name'],
                            'months' => $category['months'],
                            'months_previous' => $category['months_previous'],
                            'months_two_years_ago' => $category['months_two_years_ago'],
                            'sub_breakdown' => collect($category['sub_rows'] ?? [])->filter(fn ($sub) => ($sub['name'] ?? '—') !== '—')->map(fn ($sub) => [
                                'name' => $sub['name'],
                                'total' => $sub['subtotal'],
                            ])->values(),
                            'total' => $category['total'],
                        ];
                        $hasRealSubcategories = collect($category['sub_rows'] ?? [])->contains(fn ($sub) => ($sub['name'] ?? '—') !== '—');
                    @endphp
                    <tbody>
                        <tr class="bg-slate-50 border-b border-slate-200">
                            <td class="px-4 py-3 font-medium">
                                <div class="inline-flex w-full items-center justify-between gap-2">
                                    <div class="inline-flex items-center gap-2">
                                        @if($hasRealSubcategories)
                                            <button type="button"
                                                    @click="toggleCategory('{{ $categoryKey }}')"
                                                    class="inline-flex h-6 w-6 items-center justify-center rounded border border-slate-300 text-sm font-bold text-slate-700 hover:bg-slate-100"
                                                    :aria-label="isCategoryExpanded('{{ $categoryKey }}') ? 'Comprimi sottocategorie' : 'Espandi sottocategorie'"
                                                    :title="isCategoryExpanded('{{ $categoryKey }}') ? 'Comprimi sottocategorie' : 'Espandi sottocategorie'">
                                                <span x-text="isCategoryExpanded('{{ $categoryKey }}') ? '-' : '+'"></span>
                                            </button>
                                        @endif
                                        <button type="button"
                                                @click='openPanel(@json($categoryPayload), "{{ $categoryKey }}")'
                                                class="inline-flex h-6 w-6 items-center justify-center rounded border border-slate-300 text-slate-600 hover:bg-slate-100"
                                                :aria-label="isOpen('{{ $categoryKey }}') ? 'Chiudi dashboard categoria' : 'Apri dashboard categoria'"
                                                :title="isOpen('{{ $categoryKey }}') ? 'Chiudi dashboard categoria' : 'Apri dashboard categoria'">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path d="M3 3a1 1 0 011-1h12a1 1 0 011 1v14a1 1 0 01-1 1H4a1 1 0 01-1-1V3zm2 10v3h2v-3H5zm4-5v8h2V8H9zm4 3v5h2v-5h-2z"/>
                                            </svg>
                                        </button>
                                        <button type="button"
                                                @click='openPanel(@json($categoryPayload), "{{ $categoryKey }}")'
                                                class="text-left text-blue-700 hover:underline font-semibold">
                                            {{ $category['name'] }}
                                        </button>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-500 text-xs uppercase tracking-wide">Totale categoria</td>
                            <td class="px-4 py-3 text-right font-bold {{ $activeTab === 'entrate' ? 'text-emerald-700' : 'text-rose-700' }}">
                                € {{ number_format((float) $category['total'], 2, ',', '.') }}
                            </td>
                        </tr>

                        @include('corrente._mini_dashboard_row', ['targetKey' => $categoryKey])

                        @foreach($category['sub_rows'] as $index => $sub)
                            @if($sub['name'] === '—')
                                @continue
                            @endif
                            @php
                                $subKey = 'sub-' . $sub['id'] . '-' . $index;
                                $subPayload = [
                                    'kind' => 'Sub-categoria',
                                    'name' => $sub['name'],
                                    'category_name' => $category['name'],
                                    'months' => $sub['months'],
                                    'months_previous' => $sub['months_previous'],
                                    'months_two_years_ago' => $sub['months_two_years_ago'],
                                    'total' => $sub['subtotal'],
                                ];
                            @endphp
                            <tr x-show="isCategoryExpanded('{{ $categoryKey }}')" class="border-b border-slate-100">
                                <td class="px-4 py-3 text-slate-400">↳</td>

                                <td class="px-4 py-3 text-slate-600">
                                    <button type="button"
                                            @click='openPanel(@json($subPayload), "{{ $subKey }}")'
                                            class="inline-flex w-full items-center gap-2 text-left text-blue-700 hover:underline">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path d="M3 3a1 1 0 011-1h12a1 1 0 011 1v14a1 1 0 01-1 1H4a1 1 0 01-1-1V3zm2 10v3h2v-3H5zm4-5v8h2V8H9zm4 3v5h2v-5h-2z"/>
                                        </svg>
                                        <span>{{ $sub['name'] }}</span>
                                    </button>
                                </td>

                                <td class="px-4 py-3 text-right font-semibold {{ $activeTab === 'entrate' ? 'text-emerald-600' : 'text-rose-600' }}">
                                    € {{ number_format((float) $sub['subtotal'], 2, ',', '.') }}
                                </td>
                            </tr>
                            @include('corrente._mini_dashboard_row', ['targetKey' => $subKey, 'parentCategoryKey' => $categoryKey])
                        @endforeach
                    </tbody>
                @empty
                    <tbody>
                        <tr>
                            <td colspan="3" class="px-4 py-8 text-center text-slate-500">Nessuna categoria disponibile.</td>
                        </tr>
                    </tbody>
                @endforelse

                @if($categories->isNotEmpty())
                    <tfoot class="bg-slate-900 text-white">
                        <tr>
                            <td class="px-4 py-3 font-semibold" colspan="2">Totale generale {{ $activeTab === 'entrate' ? 'Entrate' : 'Uscite' }}</td>
                            <td class="px-4 py-3 text-right font-bold">€ {{ number_format((float) $grandTotal, 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <script>
        function correnteDashboard() {
            return {
                openKeys: [],
                panels: {},
                categoryPanels: @js($categoryPanels),
                expandableCategoryKeys: @js($expandableCategoryKeys),
                expandedCategories: @js($initialExpandedCategoryKeys),
                expandedStorageKey: 'bn_corrente_expanded_' + @js($activeTab),
                labels: ['Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu', 'Lug', 'Ago', 'Set', 'Ott', 'Nov', 'Dic'],
                currentYear: {{ (int) ($currentYear ?? now()->format('Y')) }},
                previousYear: {{ (int) ($previousYear ?? now()->subYear()->format('Y')) }},
                twoYearsAgoYear: {{ (int) ($twoYearsAgoYear ?? now()->subYears(2)->format('Y')) }},

                init() {
                    try {
                        const raw = localStorage.getItem(this.expandedStorageKey);
                        if (raw) {
                            const parsed = JSON.parse(raw);
                            if (Array.isArray(parsed)) {
                                this.expandedCategories = parsed.filter(key => this.expandableCategoryKeys.includes(key));
                            }
                        }
                    } catch (e) {}
                },

                openPanel(payload, key) {
                    if (this.isOpen(key)) {
                        this.openKeys = this.openKeys.filter(k => k !== key);
                        return;
                    }

                    this.panels[key] = payload;
                    this.openKeys.push(key);
                },

                isOpen(key) {
                    return this.openKeys.includes(key);
                },

                collapseAll() {
                    this.openKeys = [];
                },

                toggleCategory(key) {
                    if (this.isCategoryExpanded(key)) {
                        this.expandedCategories = this.expandedCategories.filter(k => k !== key);
                        this.persistExpanded();
                        return;
                    }
                    this.expandedCategories.push(key);
                    this.persistExpanded();
                },

                isCategoryExpanded(key) {
                    return this.expandedCategories.includes(key);
                },

                expandAllSubcategories() {
                    this.expandedCategories = [...this.expandableCategoryKeys];
                    this.persistExpanded();
                },

                collapseAllSubcategories() {
                    this.expandedCategories = [];
                    this.persistExpanded();
                },

                persistExpanded() {
                    try { localStorage.setItem(this.expandedStorageKey, JSON.stringify(this.expandedCategories)); } catch (e) {}
                },

                panelFor(key) {
                    return this.panels[key] || { kind: '', name: '', category_name: '', months: [], months_previous: [], months_two_years_ago: [], sub_breakdown: [], total: 0 };
                },

                panelTitle(key) {
                    const panel = this.panelFor(key);
                    if (!panel?.name) return '';
                    if (panel.kind === 'Categoria') return 'Categoria: ' + panel.category_name;
                    return 'Categoria: ' + panel.category_name + ' - Sub-categoria: ' + panel.name;
                },

                monthlyRows(key) {
                    const panel = this.panelFor(key);
                    return this.labels.map((m, i) => ({ month: m, index: i, value: Number(panel.months?.[i] || 0) }));
                },

                monthlyRowsPrevious(key) {
                    const panel = this.panelFor(key);
                    return this.labels.map((m, i) => ({ month: m, index: i, value: Number(panel.months_previous?.[i] || 0) }));
                },

                monthlyRowsTwoYearsAgo(key) {
                    const panel = this.panelFor(key);
                    return this.labels.map((m, i) => ({ month: m, index: i, value: Number(panel.months_two_years_ago?.[i] || 0) }));
                },

                monthPairs(key) {
                    const rows = this.monthlyRows(key);
                    const pairs = [];
                    for (let i = 0; i < rows.length; i += 2) {
                        pairs.push([rows[i], rows[i + 1] ?? null]);
                    }
                    return pairs;
                },

                maxAbs(key) {
                    const values = this.monthlyRows(key).map(r => Math.abs(r.value))
                        .concat(this.monthlyRowsPrevious(key).map(r => Math.abs(r.value)))
                        .concat(this.monthlyRowsTwoYearsAgo(key).map(r => Math.abs(r.value)));
                    return Math.max(1, ...values);
                },

                monthHeight(value, key) {
                    return Math.max(4, Math.round((Math.abs(Number(value)) / this.maxAbs(key)) * 120));
                },

                barColor(value, series) {
                    if (series === 'previous') return Number(value) >= 0 ? 'bg-slate-400' : 'bg-slate-500';
                    if (series === 'two_years_ago') return Number(value) >= 0 ? 'bg-amber-400' : 'bg-amber-600';
                    return Number(value) >= 0 ? 'bg-emerald-500' : 'bg-rose-500';
                },

                piePalette: ['#2563eb', '#0ea5e9', '#06b6d4', '#14b8a6', '#22c55e', '#84cc16', '#eab308', '#f97316', '#ef4444', '#ec4899', '#8b5cf6', '#6366f1'],

                pieRows(key) {
                    const panel = this.panelFor(key);
                    const rows = Array.isArray(panel.sub_breakdown) ? panel.sub_breakdown : [];
                    const prepared = rows
                        .map((r) => ({ name: r.name, value: Number(r.total || 0), abs: Math.abs(Number(r.total || 0)) }))
                        .filter((r) => r.abs > 0);
                    const totalAbs = prepared.reduce((sum, r) => sum + r.abs, 0);
                    if (totalAbs <= 0) return [];
                    return prepared.map((r, idx) => ({
                        ...r,
                        color: this.piePalette[idx % this.piePalette.length],
                        percent: (r.abs / totalAbs) * 100,
                    }));
                },

                pieGradient(key) {
                    const rows = this.pieRows(key);
                    if (!rows.length) return 'conic-gradient(#e2e8f0 0 100%)';
                    let start = 0;
                    const segments = rows.map((r) => {
                        const end = start + r.percent;
                        const segment = `${r.color} ${start}% ${end}%`;
                        start = end;
                        return segment;
                    });
                    return `conic-gradient(${segments.join(', ')})`;
                },

                formatMoney(value) {
                    return '€ ' + Number(value).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },

                formatPreviousInParens(value) {
                    const num = Number(value);
                    if (!Number.isFinite(num) || num === 0) return '(-)';
                    return '(' + this.formatMoney(num) + ')';
                },

                formatMoneyWithPrevious(currentValue, previousValue) {
                    return this.formatMoney(currentValue) + ' ' + this.formatPreviousInParens(previousValue);
                },
            };
        }
    </script>
@endsection
