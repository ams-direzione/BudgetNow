<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->boolean('show_account')->default(true);
            $table->boolean('show_office')->default(false);
            $table->timestamps();

            $table->unique('budget_id');
        });

        $now = now();

        $rows = DB::table('budgets')
            ->select('id')
            ->get()
            ->map(fn ($budget) => [
                'budget_id' => $budget->id,
                'show_account' => true,
                'show_office' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($rows !== []) {
            DB::table('budget_options')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_options');
    }
};
