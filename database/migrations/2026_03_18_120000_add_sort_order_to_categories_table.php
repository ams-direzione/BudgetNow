<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('parent_id');
        });

        $groups = DB::table('categories')
            ->select('budget_id', 'entry_type_id', 'parent_id')
            ->groupBy('budget_id', 'entry_type_id', 'parent_id')
            ->get();

        foreach ($groups as $group) {
            $rows = DB::table('categories')
                ->where('budget_id', $group->budget_id)
                ->where('entry_type_id', $group->entry_type_id)
                ->where('parent_id', $group->parent_id)
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id']);

            $order = 1;
            foreach ($rows as $row) {
                DB::table('categories')
                    ->where('id', $row->id)
                    ->update(['sort_order' => $order]);
                $order++;
            }
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
