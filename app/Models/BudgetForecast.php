<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetForecast extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_id',
        'year',
        'entry_type_id',
        'category_id',
        'single_amount',
        'months_qty',
        'total_amount',
    ];

    protected $casts = [
        'year' => 'integer',
        'single_amount' => 'decimal:2',
        'months_qty' => 'integer',
        'total_amount' => 'decimal:2',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function entryType(): BelongsTo
    {
        return $this->belongsTo(EntryType::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}

