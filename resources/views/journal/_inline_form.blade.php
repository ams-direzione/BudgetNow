{{--
  Form inline condiviso tra riga "nuovo" e riga "modifica".
  Tutti i binding usano x-model="form.*" — funziona in qualsiasi componente Alpine che esponga `form` e `errors`.
  I dati dei select vengono da window.journalData (iniettato in journal/index.blade.php).
--}}
@php
    $showAccount = (bool) ($fieldVisibility['show_account'] ?? true);
    $showOffice = (bool) ($fieldVisibility['show_office'] ?? false);
@endphp
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3">

    {{-- Numero Movimento --}}
    <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">N. Movimento</label>
        <input type="text"
               :value="form.movement_number || window.journalData.next_movement_number || 'MOV-00001'"
               readonly
               class="w-full rounded border border-slate-200 bg-slate-100 px-2 py-1.5 text-sm text-slate-500 cursor-not-allowed">
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
        <div x-id="['typelist']">
            <input type="text"
                   x-model="form.entry_type_label"
                   :list="$id('typelist')"
                   @input="
                        const prev = form.entry_type_id;
                        form.entry_type_id = window.journalTypeResolveId(form.entry_type_label);
                        if (String(prev) !== String(form.entry_type_id)) {
                            const allowed = (window.journalData.categories || []).some(c =>
                                String(c.id) === String(form.category_id) && String(c.entry_type_id) === String(form.entry_type_id)
                            );
                            if (!allowed) {
                                form.category_id = '';
                                form.category_label = '';
                            } else {
                                form.category_label = window.journalCategoryLabelById(form.category_id, form.entry_type_id);
                            }
                        }
                   "
                   @change="
                        form.entry_type_id = window.journalTypeResolveId(form.entry_type_label);
                        if (!form.entry_type_id) {
                            form.entry_type_label = '';
                            form.category_id = '';
                            form.category_label = '';
                        } else {
                            form.entry_type_label = window.journalTypeLabelById(form.entry_type_id);
                        }
                   "
                   x-init="if (!form.entry_type_label) form.entry_type_label = window.journalTypeLabelById(form.entry_type_id) || form.entry_type_name || ''"
                   placeholder="Cerca tipo..."
                   class="w-full rounded border px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                   :class="errors.entry_type_id ? 'border-rose-400 bg-rose-50' : 'border-slate-300'">
            <datalist :id="$id('typelist')">
                <template x-for="t in window.journalData.types" :key="'typ-' + t.id">
                    <option :value="t.name"></option>
                </template>
            </datalist>
        </div>
        <p x-show="errors.entry_type_id" x-text="errors.entry_type_id?.[0]"
           class="text-rose-600 text-xs mt-0.5" x-cloak></p>
    </div>

    {{-- Categoria --}}
    <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Categoria</label>
        <div x-id="['catlist']">
            <input type="text"
                   x-model="form.category_label"
                   :list="$id('catlist')"
                   :disabled="!form.entry_type_id"
                   @focus="if (!form.category_label) form.category_label = window.journalCategoryLabelById(form.category_id, form.entry_type_id)"
                   @input="form.category_id = window.journalCategoryResolveId(form.category_label, form.entry_type_id)"
                   @change="form.category_id = window.journalCategoryResolveId(form.category_label, form.entry_type_id)"
                   x-init="form.category_label = window.journalCategoryLabelById(form.category_id, form.entry_type_id)"
                   :placeholder="form.entry_type_id ? 'Cerca categoria o sub-categoria...' : 'Seleziona prima il Tipo'"
                   class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed">
            <datalist :id="$id('catlist')">
                <option value=""></option>
                <template x-for="opt in window.journalCategoryOptions(form.entry_type_id)" :key="'cat-opt-' + opt.id">
                    <option :value="opt.label"></option>
                </template>
            </datalist>
        </div>
    </div>

    {{-- Descrizione --}}
    <div class="col-span-2">
        <label class="block text-xs font-medium text-slate-600 mb-1">Descrizione</label>
        <textarea x-model="form.description" rows="2" placeholder="Descrizione…"
                  class="w-full rounded border border-slate-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-y"></textarea>
    </div>

    @if($showAccount)
        {{-- Conto di Riferimento --}}
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Conto di Riferimento <span class="text-rose-500">*</span></label>
            <div x-id="['acclist']">
                <input type="text"
                       x-model="form.reference_account_label"
                       :list="$id('acclist')"
                       @input="form.reference_account_id = window.journalAccountResolveId(form.reference_account_label)"
                       @change="
                            form.reference_account_id = window.journalAccountResolveId(form.reference_account_label);
                            if (!form.reference_account_id) {
                                form.reference_account_label = '';
                            } else {
                                form.reference_account_label = window.journalAccountLabelById(form.reference_account_id);
                            }
                       "
                       x-init="if (!form.reference_account_label) form.reference_account_label = window.journalAccountLabelById(form.reference_account_id)"
                       placeholder="Cerca conto di riferimento..."
                       class="w-full rounded border px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                       :class="errors.reference_account_id ? 'border-rose-400 bg-rose-50' : 'border-slate-300'">
                <datalist :id="$id('acclist')">
                    <template x-for="a in window.journalAccountOptions()" :key="'acc-' + a.id">
                        <option :value="a.label"></option>
                    </template>
                </datalist>
            </div>
            <p x-show="errors.reference_account_id" x-text="errors.reference_account_id?.[0]"
               class="text-rose-600 text-xs mt-0.5" x-cloak></p>
        </div>
    @endif

    @if($showOffice)
        {{-- Sede --}}
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Sede</label>
            <div x-id="['officelist']">
                <input type="text"
                       x-model="form.office_label"
                       :list="$id('officelist')"
                       @input="form.office_id = window.journalOfficeResolveId(form.office_label)"
                       @change="
                            form.office_id = window.journalOfficeResolveId(form.office_label);
                            if (!form.office_id) {
                                form.office_label = '';
                            } else {
                                form.office_label = window.journalOfficeLabelById(form.office_id);
                            }
                       "
                       x-init="if (!form.office_label) form.office_label = window.journalOfficeLabelById(form.office_id)"
                       placeholder="Cerca sede..."
                       class="w-full rounded border px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                       :class="errors.office_id ? 'border-rose-400 bg-rose-50' : 'border-slate-300'">
                <datalist :id="$id('officelist')">
                    <template x-for="o in window.journalOfficeOptions()" :key="'office-' + o.id">
                        <option :value="o.label"></option>
                    </template>
                </datalist>
            </div>
            <p x-show="errors.office_id" x-text="errors.office_id?.[0]"
               class="text-rose-600 text-xs mt-0.5" x-cloak></p>
        </div>
    @endif

    {{-- Importo --}}
    <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Importo (€) <span class="text-rose-500">*</span></label>
        <input type="number" step="0.01" min="0.01" x-model="form.amount" placeholder="0.00"
               class="w-full rounded border px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
               :class="errors.amount ? 'border-rose-400 bg-rose-50' : 'border-slate-300'">
        <p x-show="errors.amount" x-text="errors.amount?.[0]"
           class="text-rose-600 text-xs mt-0.5" x-cloak></p>
    </div>

</div>
