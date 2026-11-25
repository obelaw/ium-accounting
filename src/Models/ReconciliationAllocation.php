<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Obelaw\Ium\Accounting\Base\ModelBase;

class ReconciliationAllocation extends ModelBase
{
    protected $table = 'reconciliation_allocations';

    protected $fillable = [
        'reconciliation_id',
        'debit_line_id',
        'credit_line_id',
        'amount',
        'amount_functional_debit',
        'amount_functional_credit',
        'fx_difference',
    ];

    protected $casts = [
        'reconciliation_id' => 'integer',
        'debit_line_id' => 'integer',
        'credit_line_id' => 'integer',
        'amount' => 'integer',
        'amount_functional_debit' => 'integer',
        'amount_functional_credit' => 'integer',
        'fx_difference' => 'integer',
    ];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(Reconciliation::class, 'reconciliation_id');
    }

    public function debitLine(): BelongsTo
    {
        return $this->belongsTo(JournalEntryLine::class, 'debit_line_id');
    }

    public function creditLine(): BelongsTo
    {
        return $this->belongsTo(JournalEntryLine::class, 'credit_line_id');
    }
}
