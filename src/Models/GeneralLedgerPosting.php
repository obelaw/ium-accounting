<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Obelaw\Ium\Accounting\Base\ModelBase;
use Obelaw\Ium\Accounting\Exceptions\ImmutableJournalEntryException;

class GeneralLedgerPosting extends ModelBase
{
    public $timestamps = false;

    protected $table = 'gl_postings';

    protected $fillable = [
        'account_id',
        'journal_id',
        'journal_entry_id',
        'journal_entry_line_id',
        'fiscal_period_id',
        'cost_center_id',
        'tax_id',
        'party_type',
        'party_id',
        'posting_date',
        'currency',
        'exchange_rate',
        'debit',
        'credit',
        'amount_currency',
        'balance_after',
        'narration',
        'created_at',
    ];

    protected $casts = [
        'account_id' => 'integer',
        'journal_id' => 'integer',
        'journal_entry_id' => 'integer',
        'journal_entry_line_id' => 'integer',
        'fiscal_period_id' => 'integer',
        'cost_center_id' => 'integer',
        'tax_id' => 'integer',
        'party_id' => 'integer',
        'posting_date' => 'date:Y-m-d',
        'exchange_rate' => 'float',
        'debit' => 'integer',
        'credit' => 'integer',
        'amount_currency' => 'integer',
        'balance_after' => 'integer',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::updating(static function (self $posting): void {
            throw ImmutableJournalEntryException::cannotMutate('GL#' . $posting->id, 'update');
        });

        static::deleting(static function (self $posting): void {
            throw ImmutableJournalEntryException::cannotMutate('GL#' . $posting->id, 'delete');
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'journal_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function journalEntryLine(): BelongsTo
    {
        return $this->belongsTo(JournalEntryLine::class, 'journal_entry_line_id');
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class, 'fiscal_period_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }
}
