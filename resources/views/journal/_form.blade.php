{{--
  Partial condiviso tra create ed edit del Libro Giornale.
  Variabili attese: $entryTypes, $categories, $accounts
  In edit è disponibile anche $entry.
--}}
@php
    $showAccount = (bool) ($fieldVisibility['show_account'] ?? true);
    $showOffice = (bool) ($fieldVisibility['show_office'] ?? false);
    $selectedCategoryId = (string) old('category_id', $entry->category_id ?? '');
    $selectedCategory = $selectedCategoryId !== '' ? $categories->firstWhere('id', (int) $selectedCategoryId) : null;
    $initialParentCategoryId = (string) old(
        'parent_category_id',
        $selectedCategory ? ($selectedCategory->parent_id ? $selectedCategory->parent_id : $selectedCategory->id) : ''
    );
    $initialSubCategoryId = (string) old(
        'sub_category_id',
        $selectedCategory && $selectedCategory->parent_id ? $selectedCategory->id : ''
    );
    $categoriesForJs = $categories->map(function ($c) {
        return [
            'id' => (int) $c->id,
            'name' => (string) $c->name,
            'entry_type_id' => (int) $c->entry_type_id,
            'parent_id' => $c->parent_id ? (int) $c->parent_id : null,
        ];
    })->values();
@endphp

<div class="grid gap-5 sm:grid-cols-2">

    {{-- Numero Movimento --}}
    <div>
        <label for="movement_number" class="mb-1.5 block text-sm font-medium text-slate-700">
            Numero Movimento
        </label>
        <input type="text" id="movement_number"
               value="{{ old('movement_number', $entry->movement_number ?? ($nextMovementNumber ?? 'MOV-00001')) }}"
               readonly
               class="w-full rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-sm text-slate-500 cursor-not-allowed">
    </div>

    {{-- Data --}}
    <div>
        <label for="entry_date" class="mb-1.5 block text-sm font-medium text-slate-700">
            Data <span class="text-rose-500">*</span>
        </label>
        <input type="date" id="entry_date" name="entry_date"
               value="{{ old('entry_date', isset($entry) ? $entry->entry_date->format('Y-m-d') : '') }}"
               class="w-full rounded-lg border @error('entry_date') border-rose-400 @else border-slate-300 @enderror px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        @error('entry_date')
            <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Tipo --}}
    <div>
        <label for="entry_type_id" class="mb-1.5 block text-sm font-medium text-slate-700">
            Tipo <span class="text-rose-500">*</span>
        </label>
        <select id="entry_type_id" name="entry_type_id"
                class="w-full rounded-lg border @error('entry_type_id') border-rose-400 @else border-slate-300 @enderror px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">— Seleziona tipo —</option>
            @foreach($entryTypes as $type)
                <option value="{{ $type->id }}"
                    {{ old('entry_type_id', $entry->entry_type_id ?? '') == $type->id ? 'selected' : '' }}>
                    {{ $type->name }}
                </option>
            @endforeach
        </select>
        @error('entry_type_id')
            <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Categoria / Subcategoria --}}
    <div>
        <label for="parent_category_id" class="mb-1.5 block text-sm font-medium text-slate-700">
            Categoria <span class="text-slate-400 font-normal">(opzionale)</span>
        </label>
        <select id="parent_category_id" name="parent_category_id"
                class="w-full rounded-lg border @error('category_id') border-rose-400 @else border-slate-300 @enderror px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">— Nessuna categoria —</option>
        </select>
    </div>
    <div>
        <label for="sub_category_id" class="mb-1.5 block text-sm font-medium text-slate-700">
            Subcategoria <span class="text-slate-400 font-normal">(opzionale)</span>
        </label>
        <select id="sub_category_id" name="sub_category_id"
                class="w-full rounded-lg border @error('category_id') border-rose-400 @else border-slate-300 @enderror px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">— Nessuna subcategoria —</option>
        </select>
        <input type="hidden" id="category_id_hidden" name="category_id" value="{{ $selectedCategoryId }}">
        @error('category_id')
            <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    @if($showAccount)
        {{-- Conto di Riferimento --}}
        <div>
            <label for="reference_account_id" class="mb-1.5 block text-sm font-medium text-slate-700">
                Conto di Riferimento <span class="text-rose-500">*</span>
            </label>
            <select id="reference_account_id" name="reference_account_id"
                    class="w-full rounded-lg border @error('reference_account_id') border-rose-400 @else border-slate-300 @enderror px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">— Seleziona conto —</option>
                @foreach($accounts as $account)
                    <option value="{{ $account->id }}"
                        {{ old('reference_account_id', $entry->reference_account_id ?? '') == $account->id ? 'selected' : '' }}>
                        {{ $account->name }} ({{ $account->account_code }})
                    </option>
                @endforeach
            </select>
            @error('reference_account_id')
                <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>
    @endif

    @if($showOffice)
        {{-- Sede --}}
        <div>
            <label for="office_id" class="mb-1.5 block text-sm font-medium text-slate-700">
                Sede <span class="text-slate-400 font-normal">(opzionale)</span>
            </label>
            <select id="office_id" name="office_id"
                    class="w-full rounded-lg border @error('office_id') border-rose-400 @else border-slate-300 @enderror px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">— Nessuna sede —</option>
                @foreach($offices as $office)
                    <option value="{{ $office->id }}"
                        {{ old('office_id', $entry->office_id ?? '') == $office->id ? 'selected' : '' }}>
                        {{ $office->name }}
                    </option>
                @endforeach
            </select>
            @error('office_id')
                <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>
    @endif

    {{-- Importo --}}
    <div>
        <label for="amount" class="mb-1.5 block text-sm font-medium text-slate-700">
            Importo (€) <span class="text-rose-500">*</span>
        </label>
        <input type="number" id="amount" name="amount" step="0.01" min="0.01"
               value="{{ old('amount', isset($entry) ? (float) $entry->amount : '') }}"
               placeholder="0.00"
               class="w-full rounded-lg border @error('amount') border-rose-400 @else border-slate-300 @enderror px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        @error('amount')
            <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </div>


</div>

{{-- Descrizione (full width) --}}
<div class="mt-5">
    <label for="description" class="mb-1.5 block text-sm font-medium text-slate-700">
        Descrizione <span class="text-slate-400 font-normal">(opzionale)</span>
    </label>
    <textarea id="description" name="description" rows="3"
              placeholder="Descrizione del movimento…"
              class="w-full rounded-lg border @error('description') border-rose-400 @else border-slate-300 @enderror px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none">{{ old('description', $entry->description ?? '') }}</textarea>
    @error('description')
        <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
    @enderror
</div>

<script>
    (function () {
        const categories = @json($categoriesForJs);

        const typeSelect = document.getElementById('entry_type_id');
        const parentSelect = document.getElementById('parent_category_id');
        const subSelect = document.getElementById('sub_category_id');
        const hiddenCategory = document.getElementById('category_id_hidden');
        if (!typeSelect || !parentSelect || !subSelect || !hiddenCategory) {
            return;
        }

        const initialParentId = '{{ $initialParentCategoryId }}';
        const initialSubId = '{{ $initialSubCategoryId }}';
        const initialCategoryId = '{{ $selectedCategoryId }}';
        let bootstrapped = false;

        const toInt = (value) => {
            const parsed = Number.parseInt(String(value || ''), 10);
            return Number.isFinite(parsed) ? parsed : null;
        };

        const currentTypeId = () => toInt(typeSelect.value);
        const rootOptions = (typeId) => categories
            .filter((c) => c.parent_id === null && c.entry_type_id === typeId)
            .sort((a, b) => a.name.localeCompare(b.name, 'it'));

        const childOptions = (parentId) => categories
            .filter((c) => c.parent_id === parentId)
            .sort((a, b) => a.name.localeCompare(b.name, 'it'));

        const fillSelect = (select, options, placeholder, selectedValue) => {
            select.innerHTML = '';
            const first = document.createElement('option');
            first.value = '';
            first.textContent = placeholder;
            select.appendChild(first);

            for (const option of options) {
                const el = document.createElement('option');
                el.value = String(option.id);
                el.textContent = option.name;
                if (String(selectedValue || '') === String(option.id)) {
                    el.selected = true;
                }
                select.appendChild(el);
            }
        };

        const syncHidden = () => {
            hiddenCategory.value = subSelect.value || parentSelect.value || '';
        };

        const refresh = () => {
            const typeId = currentTypeId();
            const roots = typeId ? rootOptions(typeId) : [];

            let selectedParent = bootstrapped ? parentSelect.value : initialParentId;
            if (!roots.some((r) => String(r.id) === String(selectedParent || ''))) {
                selectedParent = '';
            }
            fillSelect(parentSelect, roots, '— Nessuna categoria —', selectedParent);

            const parentId = toInt(parentSelect.value);
            const children = parentId ? childOptions(parentId) : [];

            let selectedSub = bootstrapped ? subSelect.value : initialSubId;
            if (!children.some((c) => String(c.id) === String(selectedSub || ''))) {
                selectedSub = '';
            }
            fillSelect(subSelect, children, '— Nessuna subcategoria —', selectedSub);
            subSelect.disabled = !parentId;

            syncHidden();
        };

        hiddenCategory.value = initialCategoryId;

        typeSelect.addEventListener('change', () => {
            parentSelect.value = '';
            subSelect.value = '';
            refresh();
        });
        parentSelect.addEventListener('change', () => {
            subSelect.value = '';
            refresh();
        });
        subSelect.addEventListener('change', syncHidden);

        refresh();
        bootstrapped = true;
    })();
</script>
