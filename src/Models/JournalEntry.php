<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Obelaw\Ium\Accounting\Base\ModelBase;
use Obelaw\Ium\Accounting\Enums\JournalEntryStatus;
use Obelaw\Ium\Accounting\Enums\JournalEntryType;
use Obelaw\Ium\Accounting\Exceptions\ImmutableJournalEntryException;

/**
 * Journal Entry (Voucher) Aggregate Root.
 *
 * Automatically maps to table `ium_accounting_journal_entries`.
 */
class JournalEntry extends ModelBase
{
    protected $table = 'journal_entries';

    protected $fillable = [
        'entry_number',
        'entry_date',
        'fiscal_period_id',
        'status',
        'entry_type',
        'narration',
        'currency',
        'exchange_rate',
        'total_debit',
        'total_credit',
        'creator_id',
        'approver_id',
        'posted_at',
        'posted_by',
        'reversed_entry_id',
        'reversal_entry_id',
        'rejection_reason',
        'source_type',
        'source_id',
        'metadata',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'fiscal_period_id' => 'integer',
        'status' => JournalEntryStatus::class,
        'entry_type' => JournalEntryType::class,
        'exchange_rate' => 'float',
        'total_debit' => 'integer',
        'total_credit' => 'integer',
        'posted_at' => 'datetime',
        'reversed_entry_id' => 'integer',
        'reversal_entry_id' => 'integer',
        'source_id' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Enforce strict ledger immutability invariant:
     * Once a journal entry is posted, it can NEVER be modified or deleted in-place.
     */
    protected static function booted(): void
    {
        parent::booted();

        static::updating(function (self $entry): void {
            $originalStatus = $entry->getOriginal('status');
            $originalStatusValue = $originalStatus instanceof JournalEntryStatus
                ? $originalStatus->value
                : (string) $originalStatus;

            // Allow setting reversal_entry_id / status change to REVERSED when linking a reversal
            if ($originalStatusValue === JournalEntryStatus::POSTED->value) {
                $dirtyKeys = array_keys($entry->getDirty());
                $allowedReversalKeys = ['status', 'reversal_entry_id', 'reversed_entry_id', 'updated_at'];
                $diff = array_diff($dirtyKeys, $allowedReversalKeys);

                if (!empty($diff)) {
                    throw ImmutableJournalEntryException::cannotMutate($entry->entry_number, 'update');
                }
            }
        });

        static::deleting(function (self $entry): void {
            if ($entry->status === JournalEntryStatus::POSTED || $entry->status === JournalEntryStatus::REVERSED) {
                throw ImmutableJournalEntryException::cannotMutate($entry->entry_number, 'delete');
            }
        });
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'journal_entry_id')->orderBy('line_number');
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class, 'fiscal_period_id');
    }

    public function postings(): HasMany
    {
        return $this->hasMany(GeneralLedgerPosting::class, 'journal_entry_id');
    }

    public function reversedEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_entry_id');
    }

    public function reversalEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_entry_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', JournalEntryStatus::POSTED->value);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', JournalEntryStatus::DRAFT->value);
    }

    public function isPosted(): bool
    {
        return $this->status === JournalEntryStatus::POSTED;
    }

    public function isDraft(): bool
    {
        return $this->status === JournalEntryStatus::DRAFT;
    }

    public function isApproved(): bool
    {
        return $this->status === JournalEntryStatus::APPROVED;
    }

    public function totalDebitMajor(?int $precision = null): float
    {
        $prec = $precision ?? 2;

        return round($this->total_debit / (10 ** $prec), $prec);
    }

    public function totalCreditMajor(?int $precision = null): float
    {
        $prec = $precision ?? 2;

        return round($this->total_credit / (10 ** $prec), $prec);
    }

    public function formatTotalDebit(?Currency $currency = null): string
    {
        if ($currency !== null) {
            return $currency->formatMinor($this->total_debit);
        }

        return number_format($this->totalDebitMajor(), 2);
    }

    public function formatTotalCredit(?Currency $currency = null): string
    {
        if ($currency !== null) {
            return $currency->formatMinor($this->total_credit);
        }

        return number_format($this->totalCreditMajor(), 2);
    }
}
