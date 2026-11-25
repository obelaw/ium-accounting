<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Obelaw\Ium\Accounting\Base\ModelBase;

class PostingRule extends ModelBase
{
    protected $table = 'posting_rules';

    protected $fillable = [
        'event_name',
        'journal_id',
        'debit_account_code',
        'credit_account_code',
        'tax_id',
        'description_template',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'journal_id' => 'integer',
        'tax_id' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'journal_id');
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
