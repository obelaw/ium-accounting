<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Obelaw\Ium\Accounting\Base\ModelBase;
use Obelaw\Ium\Accounting\Enums\JournalEntryStatus;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Enums\ReconciliationStatus;
use Obelaw\Ium\Accounting\Exceptions\ImmutableJournalEntryException;

class JournalEntryLine extends ModelBase
{
    protected $table = 'journal_entry_lines';

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'cost_center_id',
        'tax_id',
        'line_number',
        'type',
        'amount',
        'amount_functional',
        'due_date',
        'narration',
        'party_type',
        'party_id',
        'reconciled_amount',
        'reconciled_functional',
        'reconciliation_status',
        'metadata',
    ];

    protected $casts = [
        'journal_entry_id' => 'integer',
        'account_id' => 'integer',
        'cost_center_id' => 'integer',
        'tax_id' => 'integer',
        'line_number' => 'integer',
        'type' => PostingSide::class,
        'amount' => 'integer',
        'amount_functional' => 'integer',
        'due_date' => 'date:Y-m-d',
        'party_id' => 'integer',
        'reconciled_amount' => 'integer',
        'reconciled_functional' => 'integer',
        'reconciliation_status' => ReconciliationStatus::class,
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        parent::booted();

        $fetchParent = static function (self $line): ?JournalEntry {
            if ($line->journal_entry_id === null) {
                return null;
            }

            return JournalEntry::query()
                ->select(['id', 'entry_number', 'status'])
                ->find($line->journal_entry_id);
        };

        static::creating(static function (self $line) use ($fetchParent): void {
            $parent = $fetchParent($line);
            if ($parent !== null && $parent->status->isFinalized()) {
                throw ImmutableJournalEntryException::cannotMutate($parent->entry_number, 'add line to');
            }
        });

        static::updating(static function (self $line) use ($fetchParent): void {
            $parent = $fetchParent($line);
            if ($parent === null || ! $parent->status->isFinalized()) {
                return;
            }

            if ($parent->status === JournalEntryStatus::REVERSED) {
                throw ImmutableJournalEntryException::cannotMutate($parent->entry_number, 'update line on');
            }

            // On POSTED entries, ONLY open-item reconciliation tracking fields may be updated
            $dirtyKeys = array_keys($line->getDirty());
            $allowed = ['reconciled_amount', 'reconciled_functional', 'reconciliation_status', 'updated_at'];

            if (array_diff($dirtyKeys, $allowed) !== []) {
                throw ImmutableJournalEntryException::cannotMutate($parent->entry_number, 'update line on');
            }
        });

        static::deleting(static function (self $line) use ($fetchParent): void {
            $parent = $fetchParent($line);
            if ($parent !== null && $parent->status->isFinalized()) {
                throw ImmutableJournalEntryException::cannotMutate($parent->entry_number, 'delete line from');
            }
        });
    }

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

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }

    public function party(): MorphTo
    {
        return $this->morphTo();
    }

    public function residualAmount(): int
    {
        return max(0, (int) $this->amount - (int) $this->reconciled_amount);
    }

    public function residualFunctional(): int
    {
        return max(0, (int) $this->amount_functional - (int) $this->reconciled_functional);
    }
}
