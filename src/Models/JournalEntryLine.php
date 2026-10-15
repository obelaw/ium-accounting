<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Obelaw\Ium\Accounting\Base\ModelBase;
use Obelaw\Ium\Accounting\Enums\PostingSide;

/**
 * Journal Entry Line Item.
 *
 * Automatically maps to table `ium_accounting_journal_entry_lines`.
 */
class JournalEntryLine extends ModelBase
{
    protected $table = 'journal_entry_lines';

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'cost_center_id',
        'line_number',
        'type',
        'amount',
        'amount_functional',
        'narration',
        'party_type',
        'party_id',
        'metadata',
    ];

    protected $casts = [
        'journal_entry_id' => 'integer',
        'account_id' => 'integer',
        'cost_center_id' => 'integer',
        'line_number' => 'integer',
        'type' => PostingSide::class,
        'amount' => 'integer',
        'amount_functional' => 'integer',
        'party_id' => 'integer',
        'metadata' => 'array',
    ];

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function party(): MorphTo
    {
        return $this->morphTo();
    }

    public function isDebit(): bool
    {
        return $this->type === PostingSide::DEBIT;
    }

    public function isCredit(): bool
    {
        return $this->type === PostingSide::CREDIT;
    }

    public function amountMajor(?int $precision = null): float
    {
        $prec = $precision ?? 2;

        return round($this->amount / (10 ** $prec), $prec);
    }

    public function amountFunctionalMajor(?int $precision = null): ?float
    {
        if ($this->amount_functional === null) {
            return null;
        }

        $prec = $precision ?? 2;

        return round($this->amount_functional / (10 ** $prec), $prec);
    }

    public function formatAmount(?Currency $currency = null): string
    {
        if ($currency !== null) {
            return $currency->formatMinor($this->amount);
        }

        return number_format($this->amountMajor(), 2);
    }

    public function formatAmountFunctional(?Currency $currency = null): string
    {
        $val = $this->amount_functional ?? 0;
        if ($currency !== null) {
            return $currency->formatMinor($val);
        }

        return number_format($this->amountFunctionalMajor() ?? 0.0, 2);
    }
}
