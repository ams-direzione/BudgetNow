@extends('layouts.app')

@section('title', 'BudgetNow | Modifica Movimento')
@section('page-title', 'Modifica Movimento')

@section('content')
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('journal.index', ['year' => $entry->entry_date->year]) }}"
           class="text-sm text-slate-500 hover:text-slate-800">← Torna al Libro Giornale</a>

        <div class="mt-4 rounded-xl bg-white p-6 shadow-sm">
            <form action="{{ route('journal.update', $entry) }}" method="POST">
                @csrf
                @method('PUT')

                @include('journal._form')

                <div class="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-5">
                    <a href="{{ route('journal.index', ['year' => $entry->entry_date->year]) }}"
                       class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                        Annulla
                    </a>
                    <button type="submit"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors">
                        Aggiorna Movimento
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
