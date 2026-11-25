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

class JournalEntry extends ModelBase
{
    protected $table = 'journal_entries';

    protected $fillable = [
        'entry_number',
        'journal_id',
        'entry_date',
        'fiscal_period_id',
        'status',
        'entry_type',
        'reference',
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
        'idempotency_key',
        'source_type',
        'source_id',
        'metadata',
    ];

    protected $casts = [
        'journal_id' => 'integer',
        'entry_date' => 'date:Y-m-d',
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

    protected static function booted(): void
    {
        parent::booted();

        static::updating(function (self $entry): void {
            $originalStatus = $entry->getOriginal('status');
            $originalValue = $originalStatus instanceof JournalEntryStatus
                ? $originalStatus->value
                : (string) $originalStatus;

            if ($originalValue === JournalEntryStatus::REVERSED->value) {
                throw ImmutableJournalEntryException::cannotMutate($entry->entry_number, 'update');
            }

            if ($originalValue === JournalEntryStatus::POSTED->value) {
                $dirtyKeys = array_keys($entry->getDirty());
                $allowed = ['status', 'reversal_entry_id', 'reversed_entry_id', 'updated_at'];

                if (array_diff($dirtyKeys, $allowed) !== []) {
                    throw ImmutableJournalEntryException::cannotMutate($entry->entry_number, 'update');
                }

                if ($entry->isDirty('status')) {
                    $newStatus = $entry->status instanceof JournalEntryStatus
                        ? $entry->status->value
                        : (string) $entry->status;

                    if ($newStatus !== JournalEntryStatus::REVERSED->value) {
                        throw ImmutableJournalEntryException::cannotMutate($entry->entry_number, 'update');
                    }
                }
            }
        });

        static::deleting(function (self $entry): void {
            $originalStatus = $entry->getOriginal('status');
            $originalValue = $originalStatus instanceof JournalEntryStatus
                ? $originalStatus->value
                : (string) $originalStatus;

            $currentFinalized = $entry->status instanceof JournalEntryStatus && $entry->status->isFinalized();
            $originalFinalized = in_array($originalValue, [
                JournalEntryStatus::POSTED->value,
                JournalEntryStatus::REVERSED->value,
            ], true);

            if ($currentFinalized || $originalFinalized) {
                throw ImmutableJournalEntryException::cannotMutate($entry->entry_number, 'delete');
            }
        });
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'journal_id');
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
}
