<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('movement_number')->unique();
            $table->date('entry_date');
            $table->enum('type', ['entrata', 'uscita']);
            $table->string('category');
            $table->string('item_code');
            $table->text('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->foreignId('reference_account_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();

            $table->index('entry_date');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
