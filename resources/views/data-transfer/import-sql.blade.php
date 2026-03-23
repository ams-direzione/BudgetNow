@extends('layouts.app')

@section('title', 'BudgetNow | Import SQL')
@section('page-title', 'Import SQL Database')

@section('content')
<div class="max-w-3xl space-y-4">
    <div class="rounded-xl bg-white p-5 shadow-sm border border-slate-200">
        <h2 class="text-base font-semibold text-slate-800">Importa SQL completo del database</h2>
        <p class="mt-1 text-sm text-slate-500">
            Carica un file `.sql` esportato da BudgetNow o MySQL. L'import esegue le query in sequenza.
        </p>

        <form method="POST" action="{{ route('data-transfer.import.sql.run') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
            @csrf

            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">File SQL</label>
                <input type="file" name="sql_file" accept=".sql,.txt" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('sql_file')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="submit"
                        onclick="return confirm('Confermi l\'import SQL completo del database?');"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors">
                    Avvia Import SQL
                </button>
                <a href="{{ route('journal.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 transition-colors">
                    Torna al Libro Giornale
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
