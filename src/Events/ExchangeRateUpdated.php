<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Obelaw\Ium\Accounting\Models\ExchangeRate;

final class ExchangeRateUpdated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly ExchangeRate $exchangeRate,
        public readonly ?string $actorId = null
    ) {}
}
