<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalImportTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_id',
        'name',
        'delimiter',
        'date_column',
        'description_column',
        'amount_column',
        'budget_voice_column',
        'date_format',
        'entry_type_id',
        'reference_account_id',
        'office_id',
        'voice_category_map',
    ];

    protected $casts = [
        'voice_category_map' => 'array',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function entryType(): BelongsTo
    {
        return $this->belongsTo(EntryType::class);
    }

    public function referenceAccount(): BelongsTo
    {
        return $this->belongsTo(ReferenceAccount::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }
}
