<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DataTransferController;
use App\Http\Controllers\EntryTypeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JournalImportController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\OptionController;
use App\Http\Controllers\ReferenceAccountController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CorrenteController;
use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\PreventivoController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::get('/dashboard', HomeController::class)->name('home');
Route::resource('libro-giornale', JournalEntryController::class)
    ->names('journal')
    ->parameters(['libro-giornale' => 'entry'])
    ->except(['show']);
Route::get('libro-giornale/import/csv', [JournalImportController::class, 'create'])->name('journal.import.csv.create');
Route::post('libro-giornale/import/csv/preview', [JournalImportController::class, 'preview'])->name('journal.import.csv.preview');
Route::post('libro-giornale/import/csv/store', [JournalImportController::class, 'store'])->name('journal.import.csv.store');
Route::get('import-export/import/sql', [DataTransferController::class, 'importSqlForm'])->name('data-transfer.import.sql.form');
Route::post('import-export/import/sql', [DataTransferController::class, 'importSql'])->name('data-transfer.import.sql.run');
Route::get('import-export/export/sql', [DataTransferController::class, 'exportSqlForm'])->name('data-transfer.export.sql.form');
Route::get('import-export/export/sql/download', [DataTransferController::class, 'exportSql'])->name('data-transfer.export.sql.download');

Route::resource('tipi', EntryTypeController::class)->parameters(['tipi' => 'tipo'])->except(['show']);

// Categorie filtrate per tipo di movimento
Route::get('categorie/{tipo}', [CategoryController::class, 'index'])
    ->whereNumber('tipo')
    ->name('categorie.index');
Route::post('categorie/{tipo}/layout', [CategoryController::class, 'saveLayout'])
    ->whereNumber('tipo')
    ->name('categorie.layout.save');
Route::resource('categorie', CategoryController::class)
    ->parameters(['categorie' => 'categoria'])
    ->except(['index', 'show']);

Route::resource('conti-riferimento', ReferenceAccountController::class)
    ->parameters(['conti-riferimento' => 'contiRiferimento'])
    ->except(['show']);

Route::resource('sedi', OfficeController::class)
    ->parameters(['sedi' => 'sedi'])
    ->except(['show']);

Route::get('opzioni', [OptionController::class, 'edit'])->name('opzioni.edit');
Route::put('opzioni', [OptionController::class, 'update'])->name('opzioni.update');

Route::resource('budget', BudgetController::class)
    ->parameters(['budget' => 'budget'])
    ->except(['show']);

Route::post('budget/{budget}/switch', [BudgetController::class, 'switch'])->name('budget.switch');
Route::post('budget/{budget}/duplicate', [BudgetController::class, 'duplicate'])->name('budget.duplicate');

Route::get('corrente', fn () => redirect()->route('corrente.entrate', request()->query()))->name('corrente.index');
Route::get('corrente/entrate', [CorrenteController::class, 'entrate'])->name('corrente.entrate');
Route::get('corrente/uscite', [CorrenteController::class, 'uscite'])->name('corrente.uscite');

Route::get('preventivo', fn () => redirect()->route('preventivo.entrate', request()->query()))->name('preventivo.index');
Route::get('preventivo/entrate', [PreventivoController::class, 'entrate'])->name('preventivo.entrate');
Route::get('preventivo/uscite', [PreventivoController::class, 'uscite'])->name('preventivo.uscite');
Route::post('preventivo/entrate', [PreventivoController::class, 'saveEntrate'])->name('preventivo.entrate.save');
Route::post('preventivo/uscite', [PreventivoController::class, 'saveUscite'])->name('preventivo.uscite.save');

Route::get('analisi', fn () => redirect()->route('analysis.flows', request()->query()))->name('analysis.index');
Route::get('analisi/flussi', [AnalysisController::class, 'flows'])->name('analysis.flows');
