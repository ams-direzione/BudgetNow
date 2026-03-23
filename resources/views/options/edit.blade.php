@extends('layouts.app')

@section('title', 'BudgetNow | Opzioni')
@section('page-title', 'Opzioni')

@section('content')
    <div class="mx-auto max-w-xl">
        <div class="rounded-xl bg-white p-6 shadow-sm">
            <form action="{{ route('opzioni.update') }}" method="POST">
                @csrf
                @method('PUT')

                <h2 class="text-base font-semibold text-slate-800">Campi visibili nel Libro Giornale</h2>
                <p class="mt-1 text-sm text-slate-500">Configura quali campi mostrare nella tabella e nei form del Libro Giornale.</p>

                <div class="mt-6 space-y-4">
                    <div>
                        <label for="show_account" class="mb-1.5 block text-sm font-medium text-slate-700">Conto</label>
                        <select id="show_account" name="show_account"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="1" @selected(old('show_account', (int) $settings->show_account) === 1)>Mostra</option>
                            <option value="0" @selected(old('show_account', (int) $settings->show_account) === 0)>Nascondi</option>
                        </select>
                    </div>

                    <div>
                        <label for="show_office" class="mb-1.5 block text-sm font-medium text-slate-700">Sede</label>
                        <select id="show_office" name="show_office"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="1" @selected(old('show_office', (int) $settings->show_office) === 1)>Mostra</option>
                            <option value="0" @selected(old('show_office', (int) $settings->show_office) === 0)>Nascondi</option>
                        </select>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="submit"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors">
                        Salva opzioni
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
