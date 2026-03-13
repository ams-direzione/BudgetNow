@extends('layouts.app')

@section('title', 'BudgetNow | Modifica Budget')
@section('page-title', 'Modifica Budget')

@section('content')
    <div class="max-w-2xl">
        <a href="{{ route('budget.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Torna ai Budget</a>

        <div class="mt-3 rounded-xl bg-white p-6 shadow-sm">
            <form action="{{ route('budget.update', $budget) }}" method="POST">
                @csrf
                @method('PUT')

                <div>
                    <label for="name" class="mb-1.5 block text-sm font-medium text-slate-700">Nome Budget <span class="text-rose-500">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name', $budget->name) }}" required
                           class="w-full rounded-lg border @error('name') border-rose-400 @else border-slate-300 @enderror px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @error('name')
                        <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-6 flex items-center justify-end gap-2">
                    <a href="{{ route('budget.index') }}"
                       class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                        Annulla
                    </a>
                    <button type="submit"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors">
                        Salva Modifiche
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
