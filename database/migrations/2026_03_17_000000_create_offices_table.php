<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->string('name', 150);
            $table->timestamps();

            $table->unique(['budget_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offices');
    }
};
