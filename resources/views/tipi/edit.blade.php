@extends('layouts.app')

@section('title', 'BudgetNow | Modifica Tipo')
@section('page-title', 'Modifica Tipo di Movimento')

@section('content')
    <div class="mx-auto max-w-lg">
        <a href="{{ route('tipi.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Torna all'elenco</a>

        <div class="mt-4 rounded-xl bg-white p-6 shadow-sm">
            <form action="{{ route('tipi.update', $tipo) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="mb-5">
                    <label for="name" class="mb-1.5 block text-sm font-medium text-slate-700">Nome</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $tipo->name) }}"
                           class="w-full rounded-lg border @error('name') border-rose-400 @else border-slate-300 @enderror px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @error('name')
                        <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('tipi.index') }}"
                       class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                        Annulla
                    </a>
                    <button type="submit"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors">
                        Aggiorna
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
