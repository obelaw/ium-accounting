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
        'amount' => 'float',
        'amount_functional' => 'float',
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
}
