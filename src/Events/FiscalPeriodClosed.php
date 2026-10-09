<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Obelaw\Ium\Accounting\Models\FiscalPeriod;

final class FiscalPeriodClosed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly FiscalPeriod $period,
        public readonly string $actorId,
        public readonly string $reason
    ) {}
}
