<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entryTypes(): HasMany
    {
        return $this->hasMany(EntryType::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function referenceAccounts(): HasMany
    {
        return $this->hasMany(ReferenceAccount::class);
    }

    public function offices(): HasMany
    {
        return $this->hasMany(Office::class);
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function journalImportTemplates(): HasMany
    {
        return $this->hasMany(JournalImportTemplate::class);
    }

    public function options(): HasOne
    {
        return $this->hasOne(BudgetOption::class);
    }
}
