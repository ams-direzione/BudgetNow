@extends('layouts.app')

@section('title', 'BudgetNow | Export SQL')
@section('page-title', 'Export SQL Database')

@section('content')
<div class="max-w-3xl space-y-4">
    <div class="rounded-xl bg-white p-5 shadow-sm border border-slate-200">
        <h2 class="text-base font-semibold text-slate-800">Esporta SQL completo del database</h2>
        <p class="mt-1 text-sm text-slate-500">
            Genera e scarica un dump SQL completo delle tabelle e dei dati correnti.
        </p>

        <div class="mt-4 flex flex-wrap gap-2">
            <a href="{{ route('data-transfer.export.sql.download') }}"
               class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 transition-colors">
                Scarica Export SQL
            </a>
            <a href="{{ route('journal.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 transition-colors">
                Torna al Libro Giornale
            </a>
        </div>
    </div>
</div>
@endsection
