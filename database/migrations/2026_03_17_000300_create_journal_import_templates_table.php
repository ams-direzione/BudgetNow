<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_import_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->string('name');
            $table->string('delimiter', 5)->default(';');
            $table->string('date_column');
            $table->string('description_column');
            $table->string('amount_column');
            $table->string('budget_voice_column')->nullable();
            $table->string('date_format', 20)->default('d.m.Y');
            $table->foreignId('entry_type_id')->constrained('entry_types')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('reference_account_id')->nullable()->constrained('reference_accounts')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete()->cascadeOnUpdate();
            $table->json('voice_category_map')->nullable();
            $table->timestamps();

            $table->unique(['budget_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_import_templates');
    }
};
