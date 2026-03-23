@extends('layouts.app')

@section('title', 'BudgetNow | Preventivo')
@section('page-title', 'Preventivo')

@section('content')
    <div x-data="preventivoPage()" class="space-y-4">
        <div class="flex items-center gap-2">
            <a href="{{ route('preventivo.entrate', ['year' => $selectedYear]) }}"
               class="rounded-lg px-4 py-2 text-sm font-medium transition-colors {{ $activeTab === 'entrate' ? 'bg-blue-600 text-white' : 'border border-slate-300 text-slate-700 hover:bg-slate-50' }}">
                Entrate
            </a>
            <a href="{{ route('preventivo.uscite', ['year' => $selectedYear]) }}"
               class="rounded-lg px-4 py-2 text-sm font-medium transition-colors {{ $activeTab === 'uscite' ? 'bg-blue-600 text-white' : 'border border-slate-300 text-slate-700 hover:bg-slate-50' }}">
                Uscite
            </a>
        </div>

        <div class="rounded-xl bg-white shadow-sm overflow-hidden">
            <table class="min-w-full text-sm table-standard">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3">Categoria</th>
                        <th class="px-4 py-3">Sub-categoria</th>
                        <th class="px-4 py-3">Anno precedente ({{ $previousYear }})</th>
                        <th class="px-4 py-3">Importo singolo</th>
                        <th class="px-4 py-3">Quantita mesi</th>
                        <th class="px-4 py-3 text-right">Importo totale</th>
                    </tr>
                </thead>

                @forelse($categories as $category)
                    <tbody>
                        <tr class="bg-slate-50 border-b border-slate-200">
                            <td class="px-4 py-3 font-semibold text-slate-900">{{ $category['name'] }}</td>
                            <td class="px-4 py-3 text-xs uppercase tracking-wide text-slate-500">Totale categoria</td>
                            <td class="px-4 py-3">
                                <input type="text"
                                       readonly
                                       value="€ {{ number_format((float) $category['previous_total'], 2, ',', '.') }}"
                                       class="w-full rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-sm text-slate-600">
                            </td>
                            <td class="px-4 py-3 text-slate-400">-</td>
                            <td class="px-4 py-3 text-slate-400">-</td>
                            <td class="px-4 py-3 text-right font-bold {{ $activeTab === 'entrate' ? 'text-emerald-700' : 'text-rose-700' }}">
                                € {{ number_format((float) $category['total'], 2, ',', '.') }}
                            </td>
                        </tr>

                        @foreach($category['sub_rows'] as $idx => $row)
                            @php
                                $formId = 'preventivo-' . $activeTab . '-' . $selectedYear . '-' . $row['id'] . '-' . $idx;
                            @endphp
                            <tr x-data="planRow({{ json_encode((float) $row['single_amount']) }}, {{ (int) $row['months_qty'] }})" class="border-b border-slate-100">
                                <td class="px-4 py-3 text-slate-400">↳</td>
                                <td class="px-4 py-3 text-slate-700">{{ $row['name'] === '—' ? $category['name'] : $row['name'] }}</td>
                                <td class="px-4 py-3">
                                    <input type="text"
                                           readonly
                                           value="€ {{ number_format((float) $row['previous_amount'], 2, ',', '.') }}"
                                           class="w-full rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-sm text-slate-700">
                                </td>
                                <td class="px-4 py-3">
                                    <input type="number"
                                           step="0.01"
                                           min="0"
                                           name="single_amount"
                                           x-model.number="singleAmount"
                                           form="{{ $formId }}"
                                           class="w-36 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </td>
                                <td class="px-4 py-3">
                                    <input type="number"
                                           min="0"
                                           max="12"
                                           name="months_qty"
                                           x-model.number="monthsQty"
                                           form="{{ $formId }}"
                                           class="w-24 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <span class="font-semibold {{ $activeTab === 'entrate' ? 'text-emerald-700' : 'text-rose-700' }}"
                                              x-text="euro(total())"></span>
                                        <form id="{{ $formId }}"
                                              method="POST"
                                              action="{{ $activeTab === 'entrate' ? route('preventivo.entrate.save') : route('preventivo.uscite.save') }}">
                                            @csrf
                                            <input type="hidden" name="year" value="{{ $selectedYear }}">
                                            <input type="hidden" name="category_id" value="{{ $row['id'] }}">
                                        </form>
                                        <button type="submit"
                                                form="{{ $formId }}"
                                                class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                            Salva
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                @empty
                    <tbody>
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-slate-500">Nessuna categoria disponibile.</td>
                        </tr>
                    </tbody>
                @endforelse

                @if($categories->isNotEmpty())
                    <tfoot class="bg-slate-900 text-white">
                        <tr>
                            <td class="px-4 py-3 font-semibold" colspan="5">Totale generale {{ $activeTab === 'entrate' ? 'Entrate' : 'Uscite' }} ({{ $currentYear }})</td>
                            <td class="px-4 py-3 text-right font-bold">€ {{ number_format((float) $grandTotal, 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <script>
        function preventivoPage() {
            return {
                euro(value) {
                    return new Intl.NumberFormat('it-IT', {
                        style: 'currency',
                        currency: 'EUR',
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    }).format(Number(value || 0));
                },
            };
        }

        function planRow(single, months) {
            return {
                singleAmount: Number(single || 0),
                monthsQty: Number(months || 0),
                total() {
                    const amount = Number(this.singleAmount || 0);
                    const qty = Number(this.monthsQty || 0);
                    return amount * qty;
                },
                euro(value) {
                    return new Intl.NumberFormat('it-IT', {
                        style: 'currency',
                        currency: 'EUR',
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    }).format(Number(value || 0));
                },
            };
        }
    </script>
@endsection
