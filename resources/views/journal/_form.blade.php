{{--
  Partial condiviso tra create ed edit del Libro Giornale.
  Variabili attese: $entryTypes, $categories, $accounts
  In edit è disponibile anche $entry.
--}}

<div class="grid gap-5 sm:grid-cols-2">

    {{-- Numero Movimento --}}
    <div>
        <label for="movement_number" class="mb-1.5 block text-sm font-medium text-slate-700">
            Numero Movimento <span class="text-rose-500">*</span>
        </label>
        <input type="text" id="movement_number" name="movement_number"
               value="{{ old('movement_number', $entry->movement_number ?? '') }}"
               placeholder="Es. MOV-2026-001"
               class="w-full rounded-lg border @error('movement_number') border-rose-400 @else border-slate-300 @enderror px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        @error('movement_number')
            <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
        @enderror
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

    {{-- Categoria --}}
    <div>
        <label for="category_id" class="mb-1.5 block text-sm font-medium text-slate-700">
            Categoria <span class="text-slate-400 font-normal">(opzionale)</span>
        </label>
        <select id="category_id" name="category_id"
                class="w-full rounded-lg border @error('category_id') border-rose-400 @else border-slate-300 @enderror px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">— Nessuna categoria —</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}"
                    {{ old('category_id', $entry->category_id ?? '') == $category->id ? 'selected' : '' }}>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
        @error('category_id')
            <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </div>

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
