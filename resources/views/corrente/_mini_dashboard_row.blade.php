@php
    $showExpression = "isOpen('{$targetKey}')";
    if (!empty($parentCategoryKey ?? null)) {
        $showExpression .= " && isCategoryExpanded('{$parentCategoryKey}')";
    }
@endphp
<tr x-show="{!! $showExpression !!}"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-y-2"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 translate-y-2"
    class="bg-white border-b border-slate-200">
    <td colspan="3" class="px-4 py-4">
        <div class="rounded-xl border border-slate-200 bg-slate-50/40 p-4">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-base font-semibold text-slate-800">
                    Dashboard <span class="text-slate-500" x-text="panelTitle('{{ $targetKey }}')"></span>
                </h3>
                <button type="button" @click="openKeys = openKeys.filter(k => k !== '{{ $targetKey }}')" class="rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50">
                    Chiudi
                </button>
            </div>

            <div class="grid gap-4 grid-cols-1 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg border border-blue-200 bg-blue-50/40 p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="inline-flex items-center gap-1.5 text-xs uppercase tracking-wide text-blue-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M3 3a1 1 0 011-1h12a1 1 0 011 1v14a1 1 0 01-1 1H4a1 1 0 01-1-1V3zm2 10v3h2v-3H5zm4-5v8h2V8H9zm4 3v5h2v-5h-2z"/>
                            </svg>
                            Andamento annuale
                        </p>
                        <div class="flex items-center gap-3 text-[11px] text-slate-500">
                            <span class="inline-flex items-center gap-1">
                                <span class="inline-block h-2.5 w-2.5 rounded bg-emerald-500"></span>
                                <span x-text="currentYear"></span>
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <span class="inline-block h-2.5 w-2.5 rounded bg-slate-400"></span>
                                <span x-text="previousYear"></span>
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <span class="inline-block h-2.5 w-2.5 rounded bg-amber-400"></span>
                                <span x-text="twoYearsAgoYear"></span>
                            </span>
                        </div>
                    </div>
                    <div class="h-44 overflow-hidden">
                        <div class="flex h-36 items-end gap-1.5">
                            <template x-for="(row, idx) in monthlyRows('{{ $targetKey }}')" :key="row.month">
                                <div class="flex flex-1 items-end gap-0.5">
                                    <div :class="barColor(row.value, 'current')"
                                         class="w-1/3 rounded-t"
                                         :style="'height:' + monthHeight(row.value, '{{ $targetKey }}') + 'px'"></div>
                                    <div :class="barColor(monthlyRowsPrevious('{{ $targetKey }}')[idx].value, 'previous')"
                                         class="w-1/3 rounded-t"
                                         :style="'height:' + monthHeight(monthlyRowsPrevious('{{ $targetKey }}')[idx].value, '{{ $targetKey }}') + 'px'"></div>
                                    <div :class="barColor(monthlyRowsTwoYearsAgo('{{ $targetKey }}')[idx].value, 'two_years_ago')"
                                         class="w-1/3 rounded-t"
                                         :style="'height:' + monthHeight(monthlyRowsTwoYearsAgo('{{ $targetKey }}')[idx].value, '{{ $targetKey }}') + 'px'"></div>
                                </div>
                            </template>
                        </div>
                        <div class="mt-1 grid grid-cols-12 gap-1 text-[10px] text-slate-400">
                            <template x-for="row in monthlyRows('{{ $targetKey }}')" :key="'label-' + row.month">
                                <div class="text-center" x-text="row.month"></div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-emerald-200 bg-emerald-50/40 p-3">
                    <p class="mb-2 inline-flex items-center gap-1.5 text-xs uppercase tracking-wide text-emerald-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 2a.75.75 0 01.75.75V9h6.25a.75.75 0 010 1.5h-6.25v6.25a.75.75 0 01-1.5 0V10.5H3a.75.75 0 010-1.5h6.25V2.75A.75.75 0 0110 2z" clip-rule="evenodd" />
                        </svg>
                        Evoluzione mese per mese
                    </p>
                    <table class="min-w-full text-xs">
                        <thead class="text-slate-500">
                            <tr>
                                <th class="py-1 text-left">Mese</th>
                                <th class="py-1 text-right">Valore</th>
                                <th class="py-1 text-left pl-4">Mese</th>
                                <th class="py-1 text-right">Valore</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="pair in monthPairs('{{ $targetKey }}')" :key="'pair-' + pair[0].month">
                                <tr class="border-t border-slate-100">
                                    <td class="py-1" x-text="pair[0].month"></td>
                                    <td class="py-1 text-right font-medium" x-text="formatMoneyWithPrevious(pair[0].value, monthlyRowsPrevious('{{ $targetKey }}')[pair[0].index]?.value)"></td>
                                    <td class="py-1 pl-4" x-text="pair[1] ? pair[1].month : ''"></td>
                                    <td class="py-1 text-right font-medium" x-text="pair[1] ? formatMoneyWithPrevious(pair[1].value, monthlyRowsPrevious('{{ $targetKey }}')[pair[1].index]?.value) : ''"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="rounded-lg border border-amber-200 bg-amber-50/50 p-3">
                    <template x-if="panelFor('{{ $targetKey }}').kind === 'Categoria'">
                        <div>
                            <p class="mb-2 inline-flex items-center gap-1.5 text-xs uppercase tracking-wide text-amber-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path d="M11 17a1 1 0 100-2h-1a1 1 0 100 2h1zM5 3a1 1 0 011-1h8a1 1 0 011 1v2H5V3zm10 4H5v2h10V7zm0 4H5v6a1 1 0 001 1h8a1 1 0 001-1v-6z"/>
                                </svg>
                                Distribuzione sottocategorie
                            </p>
                            <template x-if="pieRows('{{ $targetKey }}').length > 0">
                                <div class="flex items-start gap-4">
                                    <div class="relative h-28 w-28 shrink-0 rounded-full" :style="'background:' + pieGradient('{{ $targetKey }}')">
                                        <div class="absolute inset-4 rounded-full bg-white border border-slate-100"></div>
                                    </div>
                                    <div class="min-w-0 flex-1 space-y-1">
                                        <template x-for="row in pieRows('{{ $targetKey }}')" :key="'pie-' + row.name">
                                            <div class="flex items-center justify-between gap-2 text-xs">
                                                <span class="inline-flex min-w-0 items-center gap-1.5 text-slate-700">
                                                    <span class="inline-block h-2.5 w-2.5 rounded" :style="'background:' + row.color"></span>
                                                    <span class="truncate" x-text="row.name"></span>
                                                </span>
                                                <span class="text-slate-500" x-text="row.percent.toFixed(1) + '%'"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                            <template x-if="pieRows('{{ $targetKey }}').length === 0">
                                <p class="text-sm text-slate-500">Nessun dato disponibile per le sottocategorie.</p>
                            </template>
                        </div>
                    </template>
                    <template x-if="panelFor('{{ $targetKey }}').kind !== 'Categoria'">
                        <div class="text-sm text-slate-500">Quadrante 3: in preparazione</div>
                    </template>
                </div>

                <div class="rounded-lg border border-violet-200 bg-violet-50/40 p-3 text-sm text-violet-700">
                    <p class="mb-2 inline-flex items-center gap-1.5 text-xs uppercase tracking-wide">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 3a7 7 0 00-7 7v4a1 1 0 001 1h3v-5H5a5 5 0 1110 0h-2v5h3a1 1 0 001-1v-4a7 7 0 00-7-7z" clip-rule="evenodd" />
                        </svg>
                        Insight avanzati
                    </p>
                    Quadrante 4: in preparazione
                </div>
            </div>
        </div>
    </td>
</tr>
