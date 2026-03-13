<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Svuota le righe esistenti per permettere la migrazione dei FK non nullable
        DB::table('journal_entries')->truncate();

        Schema::table('journal_entries', function (Blueprint $table) {
            // Rimuove le colonne legacy (prima l'indice, poi la colonna)
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'category']);

            // Aggiunge le nuove FK
            $table->foreignId('entry_type_id')
                ->after('entry_date')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('category_id')
                ->nullable()
                ->after('entry_type_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropForeign(['entry_type_id']);
            $table->dropForeign(['category_id']);
            $table->dropColumn(['entry_type_id', 'category_id']);

            $table->enum('type', ['entrata', 'uscita'])->after('entry_date');
            $table->string('category')->after('type');
            $table->index('type');
        });
    }
};
