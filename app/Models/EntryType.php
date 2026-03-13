<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntryType extends Model
{
    use HasFactory;

    protected $fillable = ['budget_id', 'name'];

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }
}
