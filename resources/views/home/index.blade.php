@extends('layouts.app')

@section('title', 'BudgetNow | Home')
@section('page-title', 'Home')

@section('content')
    @php
        $monthNames = [
            1 => 'Gennaio', 2 => 'Febbraio', 3 => 'Marzo', 4 => 'Aprile',
            5 => 'Maggio', 6 => 'Giugno', 7 => 'Luglio', 8 => 'Agosto',
            9 => 'Settembre', 10 => 'Ottobre', 11 => 'Novembre', 12 => 'Dicembre',
        ];
        $monthShortNames = [
            1 => 'Gen', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'Mag', 6 => 'Giu', 7 => 'Lug', 8 => 'Ago',
            9 => 'Set', 10 => 'Ott', 11 => 'Nov', 12 => 'Dic',
        ];
        $kpiPeriodLabel = !empty($selectedMonth)
            ? ($monthNames[(int) $selectedMonth] . ' ' . $selectedYear)
            : (string) $selectedYear;
    @endphp
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        #incomePieChart,
        #expensePieChart {
            width: 100% !important;
            max-width: 100% !important;
        }
    </style>

    <div class="space-y-4 min-w-0">
        <div class="grid gap-4 grid-cols-1 sm:grid-cols-2 md:grid-cols-4">
            <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Entrate {{ $kpiPeriodLabel }}</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-600">€ {{ number_format((float) $incomeTotal, 2, ',', '.') }}</p>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Uscite {{ $kpiPeriodLabel }}</p>
            <p class="mt-2 text-2xl font-semibold text-rose-600">€ {{ number_format((float) $expenseTotal, 2, ',', '.') }}</p>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Saldo {{ $kpiPeriodLabel }}</p>
            <p class="mt-2 text-2xl font-semibold {{ $balanceTotal >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">€ {{ number_format((float) $balanceTotal, 2, ',', '.') }}</p>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Movimenti {{ $kpiPeriodLabel }}</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $movementsCount }}</p>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-4 min-w-0">
            <div class="rounded-xl bg-white p-4 shadow-sm xl:col-span-4">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm font-medium text-slate-600">Periodo</span>
                    <a href="{{ route('home', ['year' => $selectedYear]) }}"
                       class="rounded-md border px-3 py-1.5 text-sm font-medium {{ empty($selectedMonth) ? 'border-blue-400 bg-blue-50 text-blue-700' : 'border-slate-300 text-slate-600 hover:bg-slate-50' }}">
                        ANNO
                    </a>
                    @foreach($monthShortNames as $monthNumber => $monthLabel)
                        <a href="{{ route('home', ['year' => $selectedYear, 'month' => $monthNumber]) }}"
                           title="{{ $monthNames[$monthNumber] }}"
                           class="rounded-md border px-3 py-1.5 text-sm font-medium {{ (int)($selectedMonth ?? 0) === $monthNumber ? 'border-blue-400 bg-blue-50 text-blue-700' : 'border-slate-300 text-slate-600 hover:bg-slate-50' }}">
                            {{ $monthLabel }}
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="rounded-xl bg-white p-5 shadow-sm xl:col-span-2 min-w-0 overflow-hidden">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-semibold text-slate-800">Entrate per categoria</h2>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-slate-400">{{ $selectedYear }}</span>
                        <button type="button" id="incomeExpandBtn"
                                class="rounded-md border border-slate-300 px-2 py-0.5 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                                title="Ingrandisci grafico">
                            &lt;&gt;
                        </button>
                    </div>
                </div>
                <div class="h-96 min-w-0 overflow-hidden">
                    <canvas id="incomePieChart"></canvas>
                </div>
                <div id="incomeChartControls" class="mt-3 flex flex-wrap gap-2 min-w-0"></div>
            </div>

            <div class="rounded-xl bg-white p-5 shadow-sm xl:col-span-2 min-w-0 overflow-hidden">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-semibold text-slate-800">Uscite per categoria</h2>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-slate-400">{{ $selectedYear }}</span>
                        <button type="button" id="expenseExpandBtn"
                                class="rounded-md border border-slate-300 px-2 py-0.5 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                                title="Ingrandisci grafico">
                            &lt;&gt;
                        </button>
                    </div>
                </div>
                <div class="h-96 min-w-0 overflow-hidden">
                    <canvas id="expensePieChart"></canvas>
                </div>
                <div id="expenseChartControls" class="mt-3 flex flex-wrap gap-2 min-w-0"></div>
            </div>

            <div class="rounded-xl bg-white shadow-sm xl:col-span-4">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="font-semibold">Ultimi movimenti</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm table-standard">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Numero Movimento</th>
                            <th class="px-5 py-3">Data</th>
                            <th class="px-5 py-3">Tipo</th>
                            <th class="px-5 py-3">Categoria</th>
                            <th class="px-5 py-3">Importo</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        @forelse($recentEntries as $entry)
                            <tr>
                                <td class="px-5 py-3 font-medium">{{ $entry->movement_number }}</td>
                                <td class="px-5 py-3">{{ $entry->entry_date->format('d/m/Y') }}</td>
                                <td class="px-5 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $entry->entryType?->name === 'Entrata' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                        {{ $entry->entryType?->name ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">{{ $entry->category?->name ?? '—' }}</td>
                                <td class="px-5 py-3 font-semibold">€ {{ number_format((float) $entry->amount, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-8 text-center text-slate-500">Nessun movimento disponibile.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="chartModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4">
        <div class="w-full max-w-6xl rounded-xl bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <h3 id="chartModalTitle" class="text-sm font-semibold text-slate-800">Grafico</h3>
                <button type="button" id="chartModalClose"
                        class="rounded-md border border-slate-300 px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50">
                    Chiudi
                </button>
            </div>
            <div class="p-4">
                <div class="h-[70vh]">
                    <canvas id="chartModalCanvas"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
        const valueLabelPlugin = {
            id: 'valueLabelPlugin',
            afterDatasetsDraw(chart, _args, pluginOptions) {
                if (!pluginOptions?.display) return;
                const { ctx, chartArea } = chart;
                chart.data.datasets.forEach((dataset, datasetIndex) => {
                    const meta = chart.getDatasetMeta(datasetIndex);
                    meta.data.forEach((bar, index) => {
                        const rawValue = dataset.data[index];
                        if (rawValue === null || rawValue === undefined) return;
                        const text = pluginOptions.formatter ? pluginOptions.formatter(rawValue) : String(rawValue);

                        ctx.save();
                        ctx.fillStyle = pluginOptions.color || '#334155';
                        ctx.font = (pluginOptions.fontSize || 11) + 'px sans-serif';
                        ctx.textBaseline = 'middle';

                        if (chart.options.indexAxis === 'y') {
                            let x = bar.x + 6;
                            let align = 'left';
                            if (x > chartArea.right - 6) {
                                x = bar.x - 6;
                                align = 'right';
                            }
                            ctx.textAlign = align;
                            ctx.fillText(text, x, bar.y);
                        } else {
                            ctx.textAlign = 'center';
                            ctx.fillText(text, bar.x, bar.y - 8);
                        }
                        ctx.restore();
                    });
                });
            },
        };
        Chart.register(valueLabelPlugin);

        const chartPalette = ['#059669', '#0ea5e9', '#6366f1', '#f59e0b', '#ef4444', '#14b8a6', '#8b5cf6', '#84cc16', '#f97316', '#64748b'];
        const emptyConfig = {
            labels: ['Nessun dato'],
            datasets: [{ data: [1], backgroundColor: ['#e2e8f0'] }],
        };

        const incomeLabels = @json($incomeByCategoryLabels);
        const incomeValues = @json($incomeByCategoryValues);
        const incomeSubByCategory = @json($incomeSubByCategory ?? []);
        const incomeEntriesByCategorySub = @json($incomeEntriesByCategorySub ?? []);
        const incomeCategorySubCounts = @json($incomeCategorySubCounts ?? []);
        const expenseLabels = @json($expenseByCategoryLabels);
        const expenseValues = @json($expenseByCategoryValues);
        const expenseSubByCategory = @json($expenseSubByCategory ?? []);
        const expenseEntriesByCategorySub = @json($expenseEntriesByCategorySub ?? []);
        const expenseCategorySubCounts = @json($expenseCategorySubCounts ?? []);
        const selectedYear = @json($selectedYear);
        const journalIndexUrl = @json(route('journal.index'));

        function euro(v) {
            return '€ ' + Number(v).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function wrapTickLabelTwoLines(value, labels, maxCharsPerLine = 28) {
            const raw = String(labels?.[value] ?? value ?? '');
            if (!raw) return '';

            if (raw.length <= maxCharsPerLine) {
                return raw;
            }

            const words = raw.split(/\s+/).filter(Boolean);
            if (words.length <= 1) {
                return [raw.slice(0, maxCharsPerLine), raw.slice(maxCharsPerLine)];
            }

            let line1 = '';
            let line2 = '';
            for (const word of words) {
                const candidate = line1 ? (line1 + ' ' + word) : word;
                if (candidate.length <= maxCharsPerLine) {
                    line1 = candidate;
                } else {
                    line2 = line2 ? (line2 + ' ' + word) : word;
                }
            }

            if (!line1) line1 = raw.slice(0, maxCharsPerLine);
            if (!line2) line2 = raw.slice(maxCharsPerLine);
            return [line1, line2];
        }

        function categoryLabelWithCount(label, subCounts) {
            const count = Number(subCounts?.[label] ?? 0);
            return `${label} (${count})`;
        }

        function buildOverviewChartConfig(labels, values, subCounts, onBarClick) {
            if (!values.length) {
                return {
                    type: 'bar',
                    data: emptyConfig,
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, valueLabelPlugin: { display: false } } },
                };
            }

            const decoratedLabels = labels.map((label) => categoryLabelWithCount(label, subCounts));
            return {
                type: 'bar',
                data: {
                    labels: decoratedLabels,
                    datasets: [{ data: values, backgroundColor: chartPalette }],
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${euro(ctx.raw)}` } },
                        valueLabelPlugin: { display: true, formatter: (v) => euro(v), color: '#334155', fontSize: 11 },
                    },
                    scales: {
                        x: { ticks: { callback: (v) => euro(v) } },
                        y: {
                            ticks: {
                                autoSkip: false,
                                font: { size: 11 },
                                callback: function (value) {
                                    return wrapTickLabelTwoLines(value, this.chart.data.labels, 30);
                                },
                            },
                        },
                    },
                    onClick: (_evt, elements) => {
                        if (!elements?.length || typeof onBarClick !== 'function') return;
                        const idx = elements[0].index;
                        onBarClick(labels[idx]);
                    },
                },
            };
        }

        function buildSubcategoryChartConfig(labels, values, title, onBarClick) {
            if (!values.length) {
                return {
                    type: 'bar',
                    data: emptyConfig,
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, valueLabelPlugin: { display: false } } },
                };
            }

            return {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{ data: values, backgroundColor: chartPalette }],
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: { display: true, text: title },
                        legend: { display: false },
                        tooltip: { callbacks: { label: (ctx) => euro(ctx.raw) } },
                        valueLabelPlugin: { display: true, formatter: (v) => euro(v), color: '#334155', fontSize: 11 },
                    },
                    scales: {
                        x: { ticks: { callback: (v) => euro(v) } },
                        y: {
                            ticks: {
                                autoSkip: false,
                                font: { size: 11 },
                                callback: function (value) {
                                    return wrapTickLabelTwoLines(value, this.chart.data.labels, 30);
                                },
                            },
                        },
                    },
                    onClick: (_evt, elements) => {
                        if (!elements?.length || typeof onBarClick !== 'function') return;
                        const idx = elements[0].index;
                        onBarClick(labels[idx]);
                    },
                },
            };
        }

        function buildJournalEntryChartConfig(labels, values, title, onBarClick) {
            if (!values.length) {
                return {
                    type: 'bar',
                    data: emptyConfig,
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, valueLabelPlugin: { display: false } } },
                };
            }

            return {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{ data: values, backgroundColor: chartPalette }],
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: { display: true, text: title },
                        legend: { display: false },
                        tooltip: { callbacks: { label: (ctx) => euro(ctx.raw) } },
                        valueLabelPlugin: { display: true, formatter: (v) => euro(v), color: '#334155', fontSize: 11 },
                    },
                    scales: {
                        x: { ticks: { callback: (v) => euro(v) } },
                        y: {
                            ticks: {
                                autoSkip: false,
                                font: { size: 11 },
                                callback: function (value) {
                                    return wrapTickLabelTwoLines(value, this.chart.data.labels, 30);
                                },
                            },
                        },
                    },
                    onClick: (_evt, elements) => {
                        if (!elements?.length || typeof onBarClick !== 'function') return;
                        const idx = elements[0].index;
                        onBarClick(labels[idx]);
                    },
                },
            };
        }

        function goToJournal(entryTypeName, category, subcategory, description) {
            const p = new URLSearchParams();
            p.set('year', String(selectedYear));
            p.set('page', '1');
            p.set('filters[entry_type]', entryTypeName);
            if (category && category !== 'Senza categoria') p.set('filters[category]', category);
            if (subcategory && subcategory !== 'Senza sub-categoria') p.set('filters[sub_category]', subcategory);
            if (description && description !== 'Senza descrizione') p.set('filters[description]', description);
            window.location.href = journalIndexUrl + '?' + p.toString();
        }

        function initInteractiveChart(canvasId, controlsId, overviewLabels, overviewValues, subMap, entriesMap, subCounts, detailTitlePrefix, entryTypeName) {
            const canvas = document.getElementById(canvasId);
            const controls = document.getElementById(controlsId);
            let chart = new Chart(canvas, buildOverviewChartConfig(overviewLabels, overviewValues, subCounts, (category) => render(category)));
            let activeCategory = null;
            let activeSubcategory = null;
            let level = 0;

            function render(category = null) {
                if (!category) {
                    level = 0;
                    activeCategory = null;
                    activeSubcategory = null;
                    chart.destroy();
                    chart = new Chart(canvas, buildOverviewChartConfig(overviewLabels, overviewValues, subCounts, (cat) => render(cat)));
                    drawControls();
                    return;
                }

                level = 1;
                const rows = subMap?.[category] || {};
                const labels = Object.keys(rows);
                const values = Object.values(rows);
                activeCategory = category;
                activeSubcategory = null;
                chart.destroy();
                chart = new Chart(canvas, buildSubcategoryChartConfig(labels, values, `${detailTitlePrefix}: ${category}`, (sub) => renderThirdLevel(category, sub)));
                drawControls();
            }

            function renderThirdLevel(category, subcategory) {
                level = 2;
                activeCategory = category;
                activeSubcategory = subcategory;
                const rows = entriesMap?.[category]?.[subcategory] || {};
                const labels = Object.keys(rows);
                const values = Object.values(rows);
                chart.destroy();
                chart = new Chart(canvas, buildJournalEntryChartConfig(
                    labels,
                    values,
                    `Voci Libro Giornale: ${category} - ${subcategory}`,
                    (description) => goToJournal(entryTypeName, category, subcategory, description)
                ));
                drawControls();
            }

            function currentConfigForModal() {
                if (level === 0) {
                    return buildOverviewChartConfig(overviewLabels, overviewValues, subCounts);
                }
                if (level === 1 && activeCategory) {
                    const rows = subMap?.[activeCategory] || {};
                    return buildSubcategoryChartConfig(Object.keys(rows), Object.values(rows), `${detailTitlePrefix}: ${activeCategory}`);
                }
                if (level === 2 && activeCategory && activeSubcategory) {
                    const rows = entriesMap?.[activeCategory]?.[activeSubcategory] || {};
                    return buildJournalEntryChartConfig(Object.keys(rows), Object.values(rows), `Voci Libro Giornale: ${activeCategory} - ${activeSubcategory}`);
                }
                return buildOverviewChartConfig(overviewLabels, overviewValues, subCounts);
            }

            function drawControls() {
                controls.innerHTML = '';
                const resetBtn = document.createElement('button');
                resetBtn.type = 'button';
                resetBtn.textContent = 'Torna alla panoramica';
                resetBtn.className = 'rounded-md border px-2.5 py-1 text-xs ' + (level === 0 ? 'border-blue-400 text-blue-700 bg-blue-50' : 'border-slate-300 text-slate-600 hover:bg-slate-50');
                resetBtn.onclick = () => render(null);
                controls.appendChild(resetBtn);

                overviewLabels.forEach((label) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.textContent = categoryLabelWithCount(label, subCounts);
                    btn.className = 'rounded-md border px-2.5 py-1 text-xs ' + (level >= 1 && activeCategory === label ? 'border-blue-400 text-blue-700 bg-blue-50' : 'border-slate-300 text-slate-600 hover:bg-slate-50');
                    btn.onclick = () => render(label);
                    controls.appendChild(btn);
                });

                if (level === 2 && activeSubcategory) {
                    const currentSub = document.createElement('span');
                    currentSub.className = 'rounded-md border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs text-emerald-700';
                    currentSub.textContent = 'Sub-categoria: ' + activeSubcategory;
                    controls.appendChild(currentSub);
                }
            }

            drawControls();

            return {
                openModal: () => openChartModal(currentConfigForModal(), `${entryTypeName} - Grafico`),
            };
        }

        const chartModal = document.getElementById('chartModal');
        const chartModalTitle = document.getElementById('chartModalTitle');
        const chartModalCanvas = document.getElementById('chartModalCanvas');
        const chartModalClose = document.getElementById('chartModalClose');
        let modalChart = null;

        function openChartModal(config, title) {
            if (modalChart) {
                modalChart.destroy();
                modalChart = null;
            }
            chartModalTitle.textContent = title;
            chartModal.classList.remove('hidden');
            chartModal.classList.add('flex');
            modalChart = new Chart(chartModalCanvas, config);
        }

        function closeChartModal() {
            chartModal.classList.add('hidden');
            chartModal.classList.remove('flex');
            if (modalChart) {
                modalChart.destroy();
                modalChart = null;
            }
        }

        chartModalClose.addEventListener('click', closeChartModal);
        chartModal.addEventListener('click', (e) => { if (e.target === chartModal) closeChartModal(); });

        const incomeChartCtrl = initInteractiveChart('incomePieChart', 'incomeChartControls', incomeLabels, incomeValues, incomeSubByCategory, incomeEntriesByCategorySub, incomeCategorySubCounts, 'Dettaglio sottocategorie', 'Entrata');
        const expenseChartCtrl = initInteractiveChart('expensePieChart', 'expenseChartControls', expenseLabels, expenseValues, expenseSubByCategory, expenseEntriesByCategorySub, expenseCategorySubCounts, 'Dettaglio sottocategorie', 'Uscita');

        document.getElementById('incomeExpandBtn').addEventListener('click', () => incomeChartCtrl.openModal());
        document.getElementById('expenseExpandBtn').addEventListener('click', () => expenseChartCtrl.openModal());
    </script>
@endsection
