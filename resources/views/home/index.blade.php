@extends('layouts.app')

@section('title', 'BudgetNow | Home')
@section('page-title', 'Home')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Entrate {{ $selectedYear }}</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-600">€ {{ number_format((float) $incomeTotal, 2, ',', '.') }}</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Uscite {{ $selectedYear }}</p>
            <p class="mt-2 text-2xl font-semibold text-rose-600">€ {{ number_format((float) $expenseTotal, 2, ',', '.') }}</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Saldo {{ $selectedYear }}</p>
            <p class="mt-2 text-2xl font-semibold {{ $balanceTotal >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">€ {{ number_format((float) $balanceTotal, 2, ',', '.') }}</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Movimenti {{ $selectedYear }}</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $movementsCount }}</p>
        </div>
    </div>

    <div class="mt-6 rounded-xl bg-white shadow-sm">
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
@endsection
