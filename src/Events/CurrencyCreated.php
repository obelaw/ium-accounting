<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Events;

use Obelaw\Ium\Accounting\Models\Currency;

final readonly class CurrencyCreated
{
    public function __construct(public Currency $currency, public ?string $actorId = null) {}
}
