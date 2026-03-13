<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $defaultBudgetId = DB::table('budgets')->insertGetId([
            'user_id' => null,
            'name' => 'Budget Principale',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('entry_types', function (Blueprint $table) {
            $table->foreignId('budget_id')->nullable()->after('id')->constrained('budgets')->cascadeOnDelete();
        });

        Schema::table('reference_accounts', function (Blueprint $table) {
            $table->foreignId('budget_id')->nullable()->after('id')->constrained('budgets')->cascadeOnDelete();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('budget_id')->nullable()->after('id')->constrained('budgets')->cascadeOnDelete();
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreignId('budget_id')->nullable()->after('id')->constrained('budgets')->cascadeOnDelete();
        });

        DB::table('entry_types')->update(['budget_id' => $defaultBudgetId]);
        DB::table('reference_accounts')->update(['budget_id' => $defaultBudgetId]);
        DB::table('categories')->update(['budget_id' => $defaultBudgetId]);
        DB::table('journal_entries')->update(['budget_id' => $defaultBudgetId]);

        Schema::table('entry_types', function (Blueprint $table) {
            $table->unsignedBigInteger('budget_id')->nullable(false)->change();
            $table->unique(['budget_id', 'name']);
        });

        Schema::table('reference_accounts', function (Blueprint $table) {
            $table->dropUnique(['account_code']);
            $table->unsignedBigInteger('budget_id')->nullable(false)->change();
            $table->unique(['budget_id', 'account_code']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedBigInteger('budget_id')->nullable(false)->change();
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropUnique(['movement_number']);
            $table->unsignedBigInteger('budget_id')->nullable(false)->change();
            $table->unique(['budget_id', 'movement_number']);
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropUnique(['budget_id', 'movement_number']);
            $table->unique('movement_number');
            $table->dropConstrainedForeignId('budget_id');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('budget_id');
        });

        Schema::table('reference_accounts', function (Blueprint $table) {
            $table->dropUnique(['budget_id', 'account_code']);
            $table->unique('account_code');
            $table->dropConstrainedForeignId('budget_id');
        });

        Schema::table('entry_types', function (Blueprint $table) {
            $table->dropUnique(['budget_id', 'name']);
            $table->dropConstrainedForeignId('budget_id');
        });

        DB::table('budgets')->where('name', 'Budget Principale')->whereNull('user_id')->delete();
    }
};
