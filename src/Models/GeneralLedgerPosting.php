<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Obelaw\Ium\Accounting\Base\ModelBase;
use Obelaw\Ium\Accounting\Exceptions\ImmutableJournalEntryException;

/**
 * General Ledger Posting Record (Append-only immutable transaction log).
 *
 * Automatically maps to table `ium_accounting_gl_postings`.
 */
class GeneralLedgerPosting extends ModelBase
{
    protected $table = 'gl_postings';

    protected $fillable = [
        'account_id',
        'journal_entry_id',
        'journal_entry_line_id',
        'fiscal_period_id',
        'posting_date',
        'debit',
        'credit',
        'balance_after',
        'narration',
    ];

    protected $casts = [
        'account_id' => 'integer',
        'journal_entry_id' => 'integer',
        'journal_entry_line_id' => 'integer',
        'fiscal_period_id' => 'integer',
        'posting_date' => 'date',
        'debit' => 'integer',
        'credit' => 'integer',
        'balance_after' => 'integer',
    ];

    /**
     * GL Postings are completely append-only: updates and deletes are prohibited.
     */
    protected static function booted(): void
    {
        parent::booted();

        static::updating(function (self $posting): void {
            throw new ImmutableJournalEntryException('General Ledger postings are append-only and cannot be modified.');
        });

        static::deleting(function (self $posting): void {
            throw new ImmutableJournalEntryException('General Ledger postings are append-only and cannot be deleted.');
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(JournalEntryLine::class, 'journal_entry_line_id');
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class, 'fiscal_period_id');
    }

    public function debitMajor(?int $precision = null): float
    {
        $prec = $precision ?? 2;

        return round($this->debit / (10 ** $prec), $prec);
    }

    public function creditMajor(?int $precision = null): float
    {
        $prec = $precision ?? 2;

        return round($this->credit / (10 ** $prec), $prec);
    }

    public function balanceAfterMajor(?int $precision = null): float
    {
        $prec = $precision ?? 2;

        return round($this->balance_after / (10 ** $prec), $prec);
    }

    public function formatDebit(?Currency $currency = null): string
    {
        if ($currency !== null) {
            return $currency->formatMinor($this->debit);
        }

        return number_format($this->debitMajor(), 2);
    }

    public function formatCredit(?Currency $currency = null): string
    {
        if ($currency !== null) {
            return $currency->formatMinor($this->credit);
        }

        return number_format($this->creditMajor(), 2);
    }

    public function formatBalanceAfter(?Currency $currency = null): string
    {
        if ($currency !== null) {
            return $currency->formatMinor($this->balance_after);
        }

        return number_format($this->balanceAfterMajor(), 2);
    }
}
