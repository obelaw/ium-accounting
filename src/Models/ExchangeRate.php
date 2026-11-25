<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Obelaw\Ium\Accounting\Base\ModelBase;

class ExchangeRate extends ModelBase
{
    protected $table = 'exchange_rates';

    protected $fillable = [
        'currency_id',
        'currency_code',
        'rate',
        'inverse_rate',
        'effective_date',
        'source',
        'metadata',
    ];

    protected $casts = [
        'currency_id' => 'integer',
        'rate' => 'float',
        'inverse_rate' => 'float',
        'effective_date' => 'date:Y-m-d',
        'metadata' => 'array',
    ];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }
}
