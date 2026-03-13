{{--
  Form inline condiviso tra riga "nuovo" e riga "modifica".
  Tutti i binding usano x-model="form.*" — funziona in qualsiasi componente Alpine che esponga `form` e `errors`.
  I dati dei select vengono da window.journalData (iniettato in journal/index.blade.php).
--}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3">

    {{-- Numero Movimento --}}
    <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">N. Movimento <span class="text-rose-500">*</span></label>
        <input type="text" x-model="form.movement_number" placeholder="Es. MOV-001"
               class="w-full rounded border px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
               :class="errors.movement_number ? 'border-rose-400 bg-rose-50' : 'border-slate-300'">
        <p x-show="errors.movement_number" x-text="errors.movement_number?.[0]"
           class="text-rose-600 text-xs mt-0.5" x-cloak></p>
    </div>

    {{-- Data --}}
    <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Data <span class="text-rose-500">*</span></label>
        <input type="date" x-model="form.entry_date"
               class="w-full rounded border px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
               :class="errors.entry_date ? 'border-rose-400 bg-rose-50' : 'border-slate-300'">
        <p x-show="errors.entry_date" x-text="errors.entry_date?.[0]"
           class="text-rose-600 text-xs mt-0.5" x-cloak></p>
    </div>

    {{-- Tipo --}}
    <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Tipo <span class="text-rose-500">*</span></label>
        <select x-model="form.entry_type_id"
                class="w-full rounded border px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                :class="errors.entry_type_id ? 'border-rose-400 bg-rose-50' : 'border-slate-300'">
            <option value="">— Tipo —</option>
            <template x-for="t in window.journalData.types" :key="t.id">
                <option :value="String(t.id)" x-text="t.name"
                        :selected="String(t.id) === form.entry_type_id"></option>
            </template>
        </select>
        <p x-show="errors.entry_type_id" x-text="errors.entry_type_id?.[0]"
           class="text-rose-600 text-xs mt-0.5" x-cloak></p>
    </div>

    {{-- Categoria --}}
    <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Categoria</label>
        <select x-model="form.category_id"
                class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">— Nessuna —</option>
            <template x-for="c in window.journalData.categories" :key="c.id">
                <option :value="String(c.id)" x-text="c.name"
                        :selected="String(c.id) === form.category_id"></option>
            </template>
        </select>
    </div>

    {{-- Conto di Riferimento --}}
    <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Conto di Riferimento <span class="text-rose-500">*</span></label>
        <select x-model="form.reference_account_id"
                class="w-full rounded border px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                :class="errors.reference_account_id ? 'border-rose-400 bg-rose-50' : 'border-slate-300'">
            <option value="">— Conto —</option>
            <template x-for="a in window.journalData.accounts" :key="a.id">
                <option :value="String(a.id)" :x-text="a.name + ' (' + a.code + ')'"
                        :selected="String(a.id) === form.reference_account_id"></option>
            </template>
        </select>
        <p x-show="errors.reference_account_id" x-text="errors.reference_account_id?.[0]"
           class="text-rose-600 text-xs mt-0.5" x-cloak></p>
    </div>

    {{-- Importo --}}
    <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Importo (€) <span class="text-rose-500">*</span></label>
        <input type="number" step="0.01" min="0.01" x-model="form.amount" placeholder="0.00"
               class="w-full rounded border px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
               :class="errors.amount ? 'border-rose-400 bg-rose-50' : 'border-slate-300'">
        <p x-show="errors.amount" x-text="errors.amount?.[0]"
           class="text-rose-600 text-xs mt-0.5" x-cloak></p>
    </div>

    {{-- Descrizione --}}
    <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Descrizione</label>
        <input type="text" x-model="form.description" placeholder="Descrizione…"
               class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
    </div>

</div>
