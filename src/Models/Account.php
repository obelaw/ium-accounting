<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Obelaw\Ium\Accounting\Base\ModelBase;
use Obelaw\Ium\Accounting\Enums\AccountClassification;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\NormalBalance;

class Account extends ModelBase
{
    protected $table = 'accounts';

    protected $fillable = [
        'code',
        'name',
        'type',
        'classification',
        'normal_balance',
        'parent_id',
        'currency',
        'is_leaf',
        'is_reconcilable',
        'is_active',
        'current_balance',
        'metadata',
    ];

    protected $casts = [
        'type' => AccountType::class,
        'classification' => AccountClassification::class,
        'normal_balance' => NormalBalance::class,
        'parent_id' => 'integer',
        'is_leaf' => 'boolean',
        'is_reconcilable' => 'boolean',
        'is_active' => 'boolean',
        'current_balance' => 'integer',
        'metadata' => 'array',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    public function ledgerPostings(): HasMany
    {
        return $this->hasMany(GeneralLedgerPosting::class, 'account_id');
    }

    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePostingAccounts(Builder $query): Builder
    {
        return $query->where('is_leaf', true)
            ->where('classification', AccountClassification::POSTING->value);
    }

    public function scopeReconcilable(Builder $query): Builder
    {
        return $query->where('is_reconcilable', true);
    }

    public function scopeOfType(Builder $query, AccountType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    public function allowsPostings(): bool
    {
        return (bool) $this->is_leaf
            && (bool) $this->is_active
            && $this->classification === AccountClassification::POSTING;
    }

    public function isContra(): bool
    {
        return $this->normal_balance !== $this->type->naturalNormalBalance();
    }

    /**
     * Signed balance contribution to its AccountType section (flips sign for contra accounts).
     */
    public function signedSectionAmount(int $normalBalanceAmount): int
    {
        return $this->isContra() ? -$normalBalanceAmount : $normalBalanceAmount;
    }
}
