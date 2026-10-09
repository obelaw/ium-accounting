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

/**
 * Chart of Accounts (COA) Account Entity.
 *
 * Automatically maps to table `ium_accounting_accounts`.
 */
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
        'is_reconciliation',
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
        'is_reconciliation' => 'boolean',
        'is_active' => 'boolean',
        'current_balance' => 'float',
        'metadata' => 'array',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    public function ledgerPostings(): HasMany
    {
        return $this->hasMany(GeneralLedgerPosting::class, 'account_id');
    }

    /**
     * Scope: Only active accounts.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Only leaf-level posting accounts.
     */
    public function scopePostingAccounts(Builder $query): Builder
    {
        return $query->where('is_leaf', true)
            ->where('classification', AccountClassification::POSTING->value);
    }

    /**
     * Scope: Filter by AccountType.
     */
    public function scopeOfType(Builder $query, AccountType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    /**
     * Check if this account accepts line item postings.
     */
    public function allowsPostings(): bool
    {
        return $this->is_leaf && $this->classification === AccountClassification::POSTING;
    }
}
