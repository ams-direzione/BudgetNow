<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('budget_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->foreignId('entry_type_id')->constrained('entry_types')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->decimal('single_amount', 12, 2)->default(0);
            $table->unsignedTinyInteger('months_qty')->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['budget_id', 'year', 'entry_type_id', 'category_id'], 'budget_forecasts_unique_row');
            $table->index(['budget_id', 'year', 'entry_type_id'], 'budget_forecasts_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_forecasts');
    }
};

