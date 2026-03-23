@extends('layouts.app')

@section('title', 'BudgetNow | Import CSV')
@section('page-title', 'Import CSV')

@section('content')
@php
    $showOffice = (bool) ($fieldVisibility['show_office'] ?? false);
    $rowsToShow = collect($previewRows);
    $isCsvLoaded = ($tempFile ?? '') !== '' && $headers !== [];
    $detectedTemplate = $templates->firstWhere('id', $selectedTemplateId ?? null);
    $hasCompatibleTemplate = (bool) ($templateCompatible ?? false);
@endphp

<script>
window.importCsvTypes = @json(
    $entryTypes->map(fn ($t) => ['id' => (int) $t->id, 'label' => $t->name])->values()
);
window.importCsvCategories = {
    rootsByType: @json($categoryRootsByType),
    childrenByRoot: @json($categoryChildrenByRoot),
};
</script>

<div class="space-y-6" x-data="{ templatesModalOpen: false }">

    <div class="rounded-xl bg-white p-5 shadow-sm border border-slate-200">
        <h2 class="text-base font-semibold text-slate-800">1) Carica CSV e imposta mapping colonne</h2>

        <form method="POST" action="{{ route('journal.import.csv.preview') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
            @csrf
            <input type="hidden" name="year" value="{{ (int) ($selectedYear ?? now()->format('Y')) }}">
            @if($tempFile)
                <input type="hidden" name="temp_file" value="{{ $tempFile }}">
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">File CSV</label>
                    <input type="file" name="csv_file" accept=".csv,.txt"
                           onchange="if(this.files && this.files.length){ this.form.submit(); }"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <p class="text-xs text-slate-400 mt-1">Se non ricarichi il file, viene usato quello già in anteprima.</p>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-medium text-slate-600">Template rilevato</label>
                        <button type="button"
                                @click="templatesModalOpen = true"
                                class="inline-flex items-center gap-1 rounded-md border border-slate-300 px-2 py-1 text-[11px] font-medium text-slate-600 hover:bg-slate-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M11.983 1.85a1 1 0 00-1.966 0l-.175 1.05a6.973 6.973 0 00-1.555.645l-.893-.59a1 1 0 00-1.214.124l-1.39 1.39a1 1 0 00-.124 1.214l.59.893a6.973 6.973 0 00-.645 1.555l-1.05.175a1 1 0 000 1.966l1.05.175c.134.54.351 1.068.645 1.555l-.59.893a1 1 0 00.124 1.214l1.39 1.39a1 1 0 001.214.124l.893-.59c.487.294 1.015.511 1.555.645l.175 1.05a1 1 0 001.966 0l.175-1.05a6.973 6.973 0 001.555-.645l.893.59a1 1 0 001.214-.124l1.39-1.39a1 1 0 00.124-1.214l-.59-.893c.294-.487.511-1.015.645-1.555l1.05-.175a1 1 0 000-1.966l-1.05-.175a6.973 6.973 0 00-.645-1.555l.59-.893a1 1 0 00-.124-1.214l-1.39-1.39a1 1 0 00-1.214-.124l-.893.59a6.973 6.973 0 00-1.555-.645l-.175-1.05zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>
                            Template
                        </button>
                    </div>
                    <input type="text"
                           readonly
                           value="{{ $detectedTemplate ? ($detectedTemplate->name . ' (v' . ($detectedTemplate->version ?? 1) . ')') : 'Nessun template compatibile rilevato' }}"
                           class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                    @if(!empty($templateNotice))
                        <p class="text-xs mt-1 {{ $hasCompatibleTemplate ? 'text-emerald-600' : 'text-rose-600' }}">
                            {{ $templateNotice }}
                        </p>
                    @endif
                </div>
            </div>

            @if($isCsvLoaded && !$hasCompatibleTemplate)
                <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    {{ $templateNotice ?: 'Incompatibilità: nessun template compatibile per il CSV selezionato.' }}
                </div>
            @endif

            @if($isCsvLoaded && $hasCompatibleTemplate)
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Separatore</label>
                    <select name="delimiter"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm {{ $isCsvLoaded ? '' : 'bg-slate-100 text-slate-500 cursor-not-allowed' }}"
                            {{ $isCsvLoaded ? '' : 'disabled' }}>
                        @foreach([';' => ';', ',' => ',', "\t" => 'Tab', '|' => '|'] as $delimiterValue => $label)
                            <option value="{{ $delimiterValue }}" @selected(($mapping['delimiter'] ?? ';') === $delimiterValue)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @if(!$isCsvLoaded)
                        <input type="hidden" name="delimiter" value="{{ $mapping['delimiter'] ?? ';' }}">
                    @endif
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Formato data</label>
                    <select name="date_format"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm {{ $isCsvLoaded ? '' : 'bg-slate-100 text-slate-500 cursor-not-allowed' }}"
                            {{ $isCsvLoaded ? '' : 'disabled' }}>
                        @foreach(['d/m/Y', 'd.m.Y', 'Y-m-d'] as $dateFormat)
                            <option value="{{ $dateFormat }}" @selected(($mapping['date_format'] ?? 'd/m/Y') === $dateFormat)>{{ $dateFormat }}</option>
                        @endforeach
                    </select>
                    @if(!$isCsvLoaded)
                        <input type="hidden" name="date_format" value="{{ $mapping['date_format'] ?? 'd/m/Y' }}">
                    @endif
                </div>
            </div>
            @endif

            @if($isCsvLoaded && $hasCompatibleTemplate && $headers !== [])
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Colonna Data</label>
                        <select name="date_column" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                            @foreach($headers as $header)
                                <option value="{{ $header }}" @selected(($mapping['date_column'] ?? '') === $header)>{{ $header }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Colonna Descrizione</label>
                        <select name="description_column" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                            @foreach($headers as $header)
                                <option value="{{ $header }}" @selected(($mapping['description_column'] ?? '') === $header)>{{ $header }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Colonna Importo</label>
                        <select name="amount_column" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                            @foreach($headers as $header)
                                <option value="{{ $header }}" @selected(($mapping['amount_column'] ?? '') === $header)>{{ $header }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            @endif

            @if($isCsvLoaded && $hasCompatibleTemplate)
            <div class="rounded-lg border border-slate-200 p-4">
                <h3 class="text-sm font-semibold text-slate-700">Opzioni globali movimento</h3>
                <div class="mt-3 grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Tipo movimento</label>
                        <select class="w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm text-slate-500 cursor-not-allowed" disabled>
                            <option>Scelta per ogni riga in anteprima</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Conto di riferimento <span class="text-rose-500">*</span></label>
                        <select name="reference_account_id"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm {{ $isCsvLoaded ? '' : 'bg-slate-100 text-slate-500 cursor-not-allowed' }}"
                                {{ $isCsvLoaded ? 'required' : 'disabled' }}>
                            <option value="">Seleziona</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" @selected((string) ($mapping['reference_account_id'] ?? '') === (string) $account->id)>
                                    {{ $account->name }} ({{ $account->account_code }})
                                </option>
                            @endforeach
                        </select>
                        @if(!$isCsvLoaded)
                            <input type="hidden" name="reference_account_id" value="{{ $mapping['reference_account_id'] ?? '' }}">
                        @endif
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Sede</label>
                        <select name="office_id"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm {{ ($showOffice && $isCsvLoaded) ? '' : 'bg-slate-100 text-slate-500 cursor-not-allowed' }}"
                                {{ ($showOffice && $isCsvLoaded) ? '' : 'disabled' }}>
                            <option value="">{{ ($showOffice && $isCsvLoaded) ? 'Nessuna' : 'Campo non attivo' }}</option>
                            @foreach($offices as $office)
                                <option value="{{ $office->id }}" @selected((string) ($mapping['office_id'] ?? '') === (string) $office->id)>{{ $office->name }}</option>
                            @endforeach
                        </select>
                        @if(!$showOffice || !$isCsvLoaded)
                            <input type="hidden" name="office_id" value="{{ $mapping['office_id'] ?? '' }}">
                        @endif
                    </div>
                </div>
            </div>
            @endif

            @if($showPreview && $hasCompatibleTemplate)
                <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="text-xs uppercase text-slate-500">Righe totali</div>
                        <div class="text-2xl font-semibold text-slate-800">{{ $previewStats['total_rows'] }}</div>
                    </div>
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3">
                        <div class="text-xs uppercase text-emerald-700">Valide</div>
                        <div class="text-2xl font-semibold text-emerald-700">{{ $previewStats['valid_rows'] }}</div>
                    </div>
                    <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3">
                        <div class="text-xs uppercase text-rose-700">Da correggere</div>
                        <div class="text-2xl font-semibold text-rose-700">{{ $previewStats['invalid_rows'] }}</div>
                    </div>
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                        <div class="text-xs uppercase text-amber-700">Duplicati potenziali</div>
                        <div class="text-2xl font-semibold text-amber-700">{{ $previewStats['duplicate_rows'] ?? 0 }}</div>
                    </div>
                    <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3">
                        <div class="text-xs uppercase text-blue-700">Righe selezionate</div>
                        <div class="text-2xl font-semibold text-blue-700">{{ $previewStats['selected_rows'] ?? 0 }}</div>
                    </div>
                </div>
            @endif

            <div class="flex flex-wrap gap-2">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors">
                    {{ $isCsvLoaded ? 'Rianalizza CSV' : 'Carica e analizza CSV' }}
                </button>

                @if($showPreview && $hasCompatibleTemplate)
                    <button type="submit"
                            formaction="{{ route('journal.import.csv.store') }}"
                            onclick="return confirm('Confermi l\'importazione delle righe valide mostrate in anteprima?')"
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 transition-colors {{ $canImport ? '' : 'opacity-50 cursor-not-allowed' }}"
                            {{ $canImport ? '' : 'disabled' }}>
                        Conferma importazione
                    </button>
                @endif

                <a href="{{ route('journal.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 transition-colors">
                    Torna al Libro Giornale
                </a>
            </div>

            @if($showPreview && $hasCompatibleTemplate)
                <div class="rounded-xl bg-white p-5 shadow-sm border border-slate-200">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-slate-800">2) Anteprima e assegnazioni per riga</h2>
                            <p class="mt-1 text-sm text-slate-500">Scegli tipo, categoria e subcategoria per ogni riga. Le combo categoria/subcategoria si attivano in base al tipo scelto.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button"
                                    onclick="toggleImportCsvSelection(true)"
                                    class="rounded-md border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                Seleziona tutti
                            </button>
                            <button type="button"
                                    onclick="toggleImportCsvSelection(false)"
                                    class="rounded-md border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                Deseleziona tutti
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 overflow-x-hidden">
                        <table class="w-full text-[11px] table-fixed">
                            <thead class="bg-slate-50 text-[11px] uppercase text-slate-500">
                                <tr>
                                    <th class="px-2 py-1.5 text-left w-10">#</th>
                                    <th class="px-2 py-1.5 text-left w-20">Importa</th>
                                    <th class="px-2 py-1.5 text-left w-20">Data</th>
                                    <th class="px-2 py-1.5 text-left w-32">Tipo</th>
                                    <th class="px-2 py-1.5 text-left w-32">Categoria</th>
                                    <th class="px-2 py-1.5 text-left w-32">Subcategoria</th>
                                    <th class="px-2 py-1.5 text-left w-36">Descrizione</th>
                                    <th class="px-2 py-1.5 text-right w-24">Importo</th>
                                    <th class="px-2 py-1.5 text-left w-40">Esito</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rowsToShow as $row)
                                    @php
                                        $staticErrors = collect($row['errors'] ?? [])
                                            ->reject(fn ($err) => in_array($err, [
                                                'Tipo movimento non selezionato',
                                                'Categoria non selezionata',
                                                'Categoria non valida',
                                                'Categoria non coerente col tipo',
                                                'Subcategoria non valida',
                                                'Subcategoria non coerente con la categoria',
                                                'Subcategoria non coerente col tipo',
                                            ], true))
                                            ->values()
                                            ->all();
                                    @endphp
                                    <tr class="border-t border-slate-100"
                                        :class="rowClass()"
                                        x-data="{
                                            typeId: '{{ (string) ($row['entry_type_id'] ?? '') }}',
                                            typeLabel: '',
                                            showTypeList: false,
                                            parentId: '{{ (string) ($row['parent_category_id'] ?? '') }}',
                                            parentLabel: '',
                                            showParentList: false,
                                            childId: '{{ (string) ($row['child_category_id'] ?? '') }}',
                                            childLabel: '',
                                            showChildList: false,
                                            includeRow: {{ ($row['include'] ?? false) ? 'true' : 'false' }},
                                            staticErrors: @js($staticErrors),
                                            dateNotice: @js((string) ($row['date_notice'] ?? '')),
                                            potentialDuplicate: {{ ($row['potential_duplicate'] ?? false) ? 'true' : 'false' }},
                                            duplicateReason: @js((string) ($row['duplicate_reason'] ?? '')),
                                            typeOptions: window.importCsvTypes || [],
                                            parentOptions: [],
                                            childOptions: [],
                                            setTypeLabelFromId() {
                                                const match = this.typeOptions.find(opt => String(opt.id) === String(this.typeId));
                                                this.typeLabel = match ? String(match.label) : '';
                                            },
                                            setParentLabelFromId() {
                                                const match = this.parentOptions.find(opt => String(opt.id) === String(this.parentId));
                                                this.parentLabel = match ? String(match.name) : '';
                                            },
                                            setChildLabelFromId() {
                                                const match = this.childOptions.find(opt => String(opt.id) === String(this.childId));
                                                this.childLabel = match ? String(match.name) : '';
                                            },
                                            onTypeInput() {
                                                const needle = String(this.typeLabel || '').trim().toLowerCase();
                                                const exact = this.typeOptions.find(opt => String(opt.label).toLowerCase() === needle);
                                                this.typeId = exact ? String(exact.id) : '';
                                                this.showTypeList = true;
                                                this.refreshParents();
                                            },
                                            selectType(opt) {
                                                this.typeId = String(opt.id);
                                                this.typeLabel = String(opt.label);
                                                this.showTypeList = false;
                                                this.refreshParents();
                                            },
                                            filteredTypes() {
                                                const needle = String(this.typeLabel || '').trim().toLowerCase();
                                                if (!needle) {
                                                    return this.typeOptions;
                                                }
                                                return this.typeOptions.filter(opt => String(opt.label).toLowerCase().includes(needle));
                                            },
                                            onParentInput() {
                                                const needle = String(this.parentLabel || '').trim().toLowerCase();
                                                const exact = this.parentOptions.find(opt => String(opt.name).toLowerCase() === needle);
                                                this.parentId = exact ? String(exact.id) : '';
                                                this.showParentList = true;
                                                this.refreshChildren();
                                            },
                                            selectParent(opt) {
                                                this.parentId = String(opt.id);
                                                this.parentLabel = String(opt.name);
                                                this.showParentList = false;
                                                this.refreshChildren();
                                            },
                                            filteredParents() {
                                                const needle = String(this.parentLabel || '').trim().toLowerCase();
                                                if (!needle) {
                                                    return this.parentOptions;
                                                }
                                                return this.parentOptions.filter(opt => String(opt.name).toLowerCase().includes(needle));
                                            },
                                            onChildInput() {
                                                const needle = String(this.childLabel || '').trim().toLowerCase();
                                                const exact = this.childOptions.find(opt => String(opt.name).toLowerCase() === needle);
                                                this.childId = exact ? String(exact.id) : '';
                                                this.showChildList = true;
                                            },
                                            selectChild(opt) {
                                                this.childId = String(opt.id);
                                                this.childLabel = String(opt.name);
                                                this.showChildList = false;
                                            },
                                            filteredChildren() {
                                                const needle = String(this.childLabel || '').trim().toLowerCase();
                                                if (!needle) {
                                                    return this.childOptions;
                                                }
                                                return this.childOptions.filter(opt => String(opt.name).toLowerCase().includes(needle));
                                            },
                                            refreshParents() {
                                                const roots = window.importCsvCategories?.rootsByType || {};
                                                this.parentOptions = (roots[String(this.typeId)] || roots[this.typeId] || []);
                                                if (!this.parentOptions.some(opt => String(opt.id) === String(this.parentId))) {
                                                    this.parentId = '';
                                                }
                                                this.setParentLabelFromId();
                                                this.refreshChildren();
                                            },
                                            refreshChildren() {
                                                const children = window.importCsvCategories?.childrenByRoot || {};
                                                this.childOptions = (children[String(this.parentId)] || children[this.parentId] || []);
                                                if (!this.childOptions.some(opt => String(opt.id) === String(this.childId))) {
                                                    this.childId = '';
                                                }
                                                this.setChildLabelFromId();
                                            },
                                            init() {
                                                this.setTypeLabelFromId();
                                                this.refreshParents();
                                                this.$watch('typeId', () => this.refreshParents());
                                                this.$watch('parentId', () => this.refreshChildren());
                                            },
                                            assignmentErrors() {
                                                const errors = [];
                                                const typeId = String(this.typeId || '');
                                                const parentId = String(this.parentId || '');
                                                const childId = String(this.childId || '');

                                                if (!typeId) {
                                                    errors.push('Tipo movimento non selezionato');
                                                }

                                                if (!parentId) {
                                                    errors.push('Categoria non selezionata');
                                                } else {
                                                    const roots = window.importCsvCategories?.rootsByType || {};
                                                    const availableParents = roots[typeId] || roots[this.typeId] || [];
                                                    if (!availableParents.some(opt => String(opt.id) === parentId)) {
                                                        errors.push('Categoria non coerente col tipo');
                                                    }
                                                }

                                                if (childId) {
                                                    const children = window.importCsvCategories?.childrenByRoot || {};
                                                    const availableChildren = children[parentId] || children[this.parentId] || [];
                                                    if (!availableChildren.some(opt => String(opt.id) === childId)) {
                                                        errors.push('Subcategoria non coerente con la categoria');
                                                    }
                                                }

                                                return errors;
                                            },
                                            currentErrors() {
                                                if (!this.includeRow) {
                                                    return [];
                                                }
                                                return [...this.staticErrors, ...this.assignmentErrors()];
                                            },
                                            statusText() {
                                                if (!this.includeRow) {
                                                    return 'Riga esclusa da importazione';
                                                }
                                                const errors = this.currentErrors();
                                                if (errors.length > 0) {
                                                    return errors.join(', ');
                                                }
                                                if (this.dateNotice !== '') {
                                                    return this.dateNotice;
                                                }
                                                if (this.potentialDuplicate) {
                                                    return this.duplicateReason || 'Possibile duplicato';
                                                }
                                                return 'OK';
                                            },
                                            statusClass() {
                                                if (!this.includeRow) {
                                                    return 'text-slate-600';
                                                }
                                                if (this.currentErrors().length > 0) {
                                                    return 'text-rose-700';
                                                }
                                                if (this.dateNotice !== '' || this.potentialDuplicate) {
                                                    return 'text-amber-700';
                                                }
                                                return 'text-emerald-700';
                                            },
                                            rowClass() {
                                                if (!this.includeRow) {
                                                    return 'bg-slate-100/80';
                                                }
                                                if (this.currentErrors().length > 0) {
                                                    return 'bg-rose-50/60';
                                                }
                                                if (this.potentialDuplicate) {
                                                    return 'bg-amber-50/70';
                                                }
                                                return 'bg-emerald-50/60';
                                            },
                                            canEditType() {
                                                return this.includeRow;
                                            },
                                            canEditParent() {
                                                return this.includeRow && !!this.typeId;
                                            },
                                            canEditChild() {
                                                return this.includeRow && !!this.parentId;
                                            },
                                        }">
                                        <td class="px-2 py-1.5 text-slate-500" title="{{ $row['line'] }}">{{ $row['line'] }}</td>
                                        <td class="px-2 py-1.5" title="Flag per includere la riga nell'import">
                                            <input type="hidden" name="row_include[{{ $row['line'] }}]" value="0">
                                            <label class="inline-flex items-center gap-2">
                                                <input type="checkbox"
                                                       name="row_include[{{ $row['line'] }}]"
                                                       value="1"
                                                       x-model="includeRow"
                                                       class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                                @if($row['potential_duplicate'] ?? false)
                                                    <span class="inline-flex h-4 w-4 items-center justify-center rounded bg-amber-200 text-[10px] font-bold text-amber-800"
                                                          title="{{ $row['duplicate_reason'] ?: 'Duplicato' }}">D</span>
                                                @endif
                                            </label>
                                        </td>
                                        <td class="px-2 py-1.5 text-slate-700" title="{{ $row['date_raw'] ?? '—' }}">{{ $row['date_raw'] ?? '—' }}</td>
                                        <td class="px-2 py-1.5" :title="typeId ? ('Tipo ID: ' + typeId) : 'Tipo non selezionato'">
                                            <input type="hidden" name="row_entry_type[{{ $row['line'] }}]" x-model="typeId">
                                            <div class="relative">
                                                <input type="text"
                                                       x-model="typeLabel"
                                                       @focus="if(canEditType()){ showTypeList = true }"
                                                       @click="if(canEditType()){ showTypeList = true }"
                                                       @input="onTypeInput()"
                                                       @keydown.escape="showTypeList = false"
                                                       @blur="setTimeout(() => showTypeList = false, 120)"
                                                       :disabled="!canEditType()"
                                                       autocomplete="off"
                                                       class="w-full rounded-lg border border-slate-300 px-1.5 py-1 text-[11px] disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed"
                                                       placeholder="Cerca tipo...">
                                                <div x-show="showTypeList && canEditType()" x-cloak class="absolute z-30 mt-1 max-h-36 w-full overflow-y-auto overflow-x-hidden rounded-lg border border-slate-200 bg-white shadow-lg">
                                                    <template x-for="opt in filteredTypes()" :key="'type-{{ $row['line'] }}-' + opt.id">
                                                        <button type="button"
                                                                @mousedown.prevent="selectType(opt)"
                                                                :title="opt.label"
                                                                class="block w-full truncate px-2 py-1 text-left text-[11px] text-slate-700 hover:bg-slate-100"
                                                                x-text="opt.label"></button>
                                                    </template>
                                                    <div x-show="filteredTypes().length === 0" class="px-2 py-1 text-[11px] text-slate-400">Nessun risultato</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-2 py-1.5" :title="parentId ? ('Categoria ID: ' + parentId) : 'Categoria non selezionata'">
                                            <input type="hidden" name="row_parent_category[{{ $row['line'] }}]" x-model="parentId">
                                            <div class="relative">
                                                <input type="text"
                                                       x-model="parentLabel"
                                                       @focus="if(canEditParent()){ showParentList = true }"
                                                       @click="if(canEditParent()){ showParentList = true }"
                                                       @input="onParentInput()"
                                                       @keydown.escape="showParentList = false"
                                                       @blur="setTimeout(() => showParentList = false, 120)"
                                                       :disabled="!canEditParent()"
                                                       autocomplete="off"
                                                       class="w-full rounded-lg border border-slate-300 px-1.5 py-1 text-[11px] disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed"
                                                       placeholder="Cerca categoria...">
                                                <div x-show="showParentList && canEditParent()" x-cloak class="absolute z-30 mt-1 max-h-36 w-full overflow-y-auto overflow-x-hidden rounded-lg border border-slate-200 bg-white shadow-lg">
                                                    <template x-for="root in filteredParents()" :key="'root-{{ $row['line'] }}-' + root.id">
                                                        <button type="button"
                                                                @mousedown.prevent="selectParent(root)"
                                                                :title="root.name"
                                                                class="block w-full truncate px-2 py-1 text-left text-[11px] text-slate-700 hover:bg-slate-100"
                                                                x-text="root.name"></button>
                                                    </template>
                                                    <div x-show="filteredParents().length === 0" class="px-2 py-1 text-[11px] text-slate-400">Nessun risultato</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-2 py-1.5" :title="childId ? ('Subcategoria ID: ' + childId) : 'Nessuna subcategoria'">
                                            <input type="hidden" name="row_child_category[{{ $row['line'] }}]" x-model="childId">
                                            <div class="relative">
                                                <input type="text"
                                                       x-model="childLabel"
                                                       @focus="if(canEditChild()){ showChildList = true }"
                                                       @click="if(canEditChild()){ showChildList = true }"
                                                       @input="onChildInput()"
                                                       @keydown.escape="showChildList = false"
                                                       @blur="setTimeout(() => showChildList = false, 120)"
                                                       :disabled="!canEditChild()"
                                                       autocomplete="off"
                                                       class="w-full rounded-lg border border-slate-300 px-1.5 py-1 text-[11px] disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed"
                                                       placeholder="Cerca subcategoria...">
                                                <div x-show="showChildList && canEditChild()" x-cloak class="absolute z-30 mt-1 max-h-36 w-full overflow-y-auto overflow-x-hidden rounded-lg border border-slate-200 bg-white shadow-lg">
                                                    <template x-for="child in filteredChildren()" :key="'child-{{ $row['line'] }}-' + child.id">
                                                        <button type="button"
                                                                @mousedown.prevent="selectChild(child)"
                                                                :title="child.name"
                                                                class="block w-full truncate px-2 py-1 text-left text-[11px] text-slate-700 hover:bg-slate-100"
                                                                x-text="child.name"></button>
                                                    </template>
                                                    <div x-show="filteredChildren().length === 0" class="px-2 py-1 text-[11px] text-slate-400">Nessun risultato</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-2 py-1.5 text-slate-600 align-top whitespace-normal break-words leading-tight" title="{{ $row['description'] }}">
                                            {{ $row['description'] }}
                                        </td>
                                        <td class="px-2 py-1.5 text-right font-medium text-slate-800" title="{{ $row['amount'] !== null ? number_format((float) $row['amount'], 2, ',', '.') : '—' }}">{{ $row['amount'] !== null ? number_format((float) $row['amount'], 2, ',', '.') : '—' }}</td>
                                        <td class="px-2 py-1.5 whitespace-normal break-words leading-tight" :title="statusText()">
                                            <span class="text-[11px] font-medium" :class="statusClass()" x-text="statusText()"></span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </form>
    </div>

    <div x-show="templatesModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="templatesModalOpen = false">
        <div class="w-full max-w-xl rounded-xl bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <h3 class="text-sm font-semibold text-slate-800">Template presenti nel sistema</h3>
                <button type="button" @click="templatesModalOpen = false" class="rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50">Chiudi</button>
            </div>
            <div class="max-h-[60vh] overflow-auto p-4">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Nome</th>
                            <th class="px-3 py-2">Versione</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($templates as $template)
                            <tr class="border-t border-slate-100">
                                <td class="px-3 py-2 text-slate-700">{{ $template->name }}</td>
                                <td class="px-3 py-2 text-slate-600">v{{ $template->version ?? 1 }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-3 py-6 text-center text-slate-500">Nessun template disponibile.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
function toggleImportCsvSelection(selectAll) {
    document
        .querySelectorAll('input[type="checkbox"][name^="row_include["]')
        .forEach((checkbox) => {
            checkbox.checked = !!selectAll;
            checkbox.dispatchEvent(new Event('input', { bubbles: true }));
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        });
}
</script>
@endsection
