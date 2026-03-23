<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_id',
        'show_account',
        'show_office',
    ];

    protected $casts = [
        'show_account' => 'boolean',
        'show_office' => 'boolean',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }
}
