<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\EntryTypeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\ReferenceAccountController;
use App\Http\Controllers\BudgetController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::resource('libro-giornale', JournalEntryController::class)
    ->names('journal')
    ->parameters(['libro-giornale' => 'entry'])
    ->except(['show']);

Route::resource('tipi', EntryTypeController::class)->parameters(['tipi' => 'tipo'])->except(['show']);

// Categorie filtrate per tipo di movimento
Route::get('categorie/{tipo}', [CategoryController::class, 'index'])->name('categorie.index');
Route::resource('categorie', CategoryController::class)
    ->parameters(['categorie' => 'categoria'])
    ->except(['index', 'show']);

Route::resource('conti-riferimento', ReferenceAccountController::class)
    ->parameters(['conti-riferimento' => 'contiRiferimento'])
    ->except(['show']);

Route::resource('budget', BudgetController::class)
    ->parameters(['budget' => 'budget'])
    ->except(['show']);

Route::post('budget/{budget}/switch', [BudgetController::class, 'switch'])->name('budget.switch');
Route::post('budget/{budget}/duplicate', [BudgetController::class, 'duplicate'])->name('budget.duplicate');
