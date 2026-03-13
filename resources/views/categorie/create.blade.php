@extends('layouts.app')

@section('title', 'BudgetNow | Nuova Categoria')
@section('page-title', 'Nuova Categoria')

@section('content')
    <div class="mx-auto max-w-lg">
        <a href="{{ $tipo ? route('categorie.index', $tipo) : '#' }}"
           class="text-sm text-slate-500 hover:text-slate-800">← Torna all'elenco</a>

        <div class="mt-4 rounded-xl bg-white p-6 shadow-sm">
            <form action="{{ route('categorie.store') }}" method="POST">
                @csrf

                {{-- Tipo di Movimento --}}
                <div class="mb-5">
                    <label for="entry_type_id" class="mb-1.5 block text-sm font-medium text-slate-700">
                        Tipo di Movimento <span class="text-rose-500">*</span>
                    </label>
                    <select id="entry_type_id" name="entry_type_id"
                            class="w-full rounded-lg border @error('entry_type_id') border-rose-400 @else border-slate-300 @enderror px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">— Seleziona —</option>
                        @foreach($entryTypes as $et)
                            <option value="{{ $et->id }}"
                                    @selected(old('entry_type_id', $tipo?->id) == $et->id)>
                                {{ $et->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('entry_type_id')
                        <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Categoria Padre --}}
                <div class="mb-5">
                    <label for="parent_id" class="mb-1.5 block text-sm font-medium text-slate-700">Categoria Padre</label>
                    <select id="parent_id" name="parent_id"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">— Nessuna (categoria radice) —</option>
                        @foreach($parents as $parent)
                            <option value="{{ $parent->id }}" @selected(old('parent_id') == $parent->id)>
                                {{ $parent->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Nome --}}
                <div class="mb-5">
                    <label for="name" class="mb-1.5 block text-sm font-medium text-slate-700">
                        Nome <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}"
                           class="w-full rounded-lg border @error('name') border-rose-400 @else border-slate-300 @enderror px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Es. Quotidiana, Stipendio, Affitto…">
                    @error('name')
                        <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-100 pt-5">
                    <a href="{{ $tipo ? route('categorie.index', $tipo) : '#' }}"
                       class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                        Annulla
                    </a>
                    <button type="submit"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors">
                        Salva Categoria
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
