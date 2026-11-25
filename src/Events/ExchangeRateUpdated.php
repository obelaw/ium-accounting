<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Events;

use Obelaw\Ium\Accounting\Models\ExchangeRate;

final readonly class ExchangeRateUpdated
{
    public function __construct(public ExchangeRate $exchangeRate, public ?string $actorId = null) {}
}
