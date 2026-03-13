<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_id',
        'movement_number',
        'entry_date',
        'entry_type_id',
        'category_id',
        'description',
        'amount',
        'reference_account_id',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function entryType(): BelongsTo
    {
        return $this->belongsTo(EntryType::class);
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function referenceAccount(): BelongsTo
    {
        return $this->belongsTo(ReferenceAccount::class);
    }
}
