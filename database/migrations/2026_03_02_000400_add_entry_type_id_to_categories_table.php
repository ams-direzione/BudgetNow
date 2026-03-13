<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('entry_type_id')
                ->nullable()
                ->after('parent_id')
                ->constrained('entry_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\EntryType::class, 'entry_type_id');
            $table->dropColumn('entry_type_id');
        });
    }
};
