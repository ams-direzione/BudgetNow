@extends('layouts.app')

@section('title', 'BudgetNow | Analisi Flussi')
@section('page-title', 'Analisi - Flussi')

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
        $periodLabel = $selectedMonth ? ($monthNames[$selectedMonth] . ' ' . $selectedYear) : ('Anno ' . $selectedYear);
    @endphp

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <div class="space-y-4">
        <div class="rounded-xl bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-medium text-slate-600">Periodo</span>
                <a href="{{ route('analysis.flows', ['year' => $selectedYear]) }}"
                   class="rounded-md border px-3 py-1.5 text-sm font-medium {{ empty($selectedMonth) ? 'border-blue-400 bg-blue-50 text-blue-700' : 'border-slate-300 text-slate-600 hover:bg-slate-50' }}">
                    ANNO
                </a>
                @foreach($monthShortNames as $monthNumber => $monthLabel)
                    <a href="{{ route('analysis.flows', ['year' => $selectedYear, 'month' => $monthNumber]) }}"
                       title="{{ $monthNames[$monthNumber] }}"
                       class="rounded-md border px-3 py-1.5 text-sm font-medium {{ (int)($selectedMonth ?? 0) === $monthNumber ? 'border-blue-400 bg-blue-50 text-blue-700' : 'border-slate-300 text-slate-600 hover:bg-slate-50' }}">
                        {{ $monthLabel }}
                    </a>
                @endforeach
            </div>
        </div>

        <div class="grid gap-4 grid-cols-1 md:grid-cols-3">
            <div class="rounded-xl bg-white p-5 shadow-sm">
                <p class="text-sm text-slate-500">Entrate ({{ $periodLabel }})</p>
                <p class="mt-2 text-2xl font-semibold text-emerald-600">€ {{ number_format((float) $incomeTotal, 2, ',', '.') }}</p>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm">
                <p class="text-sm text-slate-500">Uscite ({{ $periodLabel }})</p>
                <p class="mt-2 text-2xl font-semibold text-rose-600">€ {{ number_format((float) $expenseTotal, 2, ',', '.') }}</p>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm">
                <p class="text-sm text-slate-500">Saldo ({{ $periodLabel }})</p>
                <p class="mt-2 text-2xl font-semibold {{ $balance >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">€ {{ number_format((float) $balance, 2, ',', '.') }}</p>
            </div>
        </div>

        <div class="rounded-xl bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-semibold text-slate-800">Confronto Entrate/Uscite</h2>
                <span class="text-xs text-slate-500">{{ $periodLabel }}</span>
            </div>
            <div class="h-80">
                <canvas id="flowsChart"></canvas>
            </div>
        </div>
    </div>

    <script>
        const flowsCtx = document.getElementById('flowsChart');

        function euro(v) {
            return '€ ' + Number(v).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        new Chart(flowsCtx, {
            type: 'bar',
            data: {
                labels: ['Entrate', 'Uscite'],
                datasets: [{
                    label: 'Importo',
                    data: [{{ (float) $incomeTotal }}, {{ (float) $expenseTotal }}],
                    backgroundColor: ['#10b981', '#f43f5e'],
                    borderRadius: 8,
                    maxBarThickness: 90,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => euro(ctx.raw)
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => euro(value)
                        }
                    }
                }
            }
        });
    </script>
@endsection
