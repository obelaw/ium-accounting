<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Obelaw\Ium\Accounting\Base\ModelBase;

class Reconciliation extends ModelBase
{
    protected $table = 'reconciliations';

    protected $fillable = [
        'reconciliation_number',
        'account_id',
        'party_type',
        'party_id',
        'reconciled_date',
        'currency',
        'total_amount',
        'fx_gain_loss_entry_id',
        'actor_id',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'account_id' => 'integer',
        'party_id' => 'integer',
        'reconciled_date' => 'date:Y-m-d',
        'total_amount' => 'integer',
        'fx_gain_loss_entry_id' => 'integer',
        'metadata' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function fxGainLossEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'fx_gain_loss_entry_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ReconciliationAllocation::class, 'reconciliation_id');
    }
}
