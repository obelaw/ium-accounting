<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Obelaw\Ium\Accounting\Base\ModelBase;
use Obelaw\Ium\Accounting\Enums\JournalType;

class Journal extends ModelBase
{
    protected $table = 'journals';

    protected $fillable = [
        'code',
        'name',
        'type',
        'sequence_prefix',
        'default_debit_account_id',
        'default_credit_account_id',
        'currency',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'type' => JournalType::class,
        'default_debit_account_id' => 'integer',
        'default_credit_account_id' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function defaultDebitAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_debit_account_id');
    }

    public function defaultCreditAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_credit_account_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'journal_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, JournalType $type): Builder
    {
        return $query->where('type', $type->value);
    }
}
