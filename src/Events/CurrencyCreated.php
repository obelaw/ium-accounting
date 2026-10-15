<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Obelaw\Ium\Accounting\Models\Currency;

final class CurrencyCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Currency $currency,
        public readonly ?string $actorId = null
    ) {}
}
