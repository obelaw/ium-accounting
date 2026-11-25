<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Events;

use Obelaw\Ium\Accounting\Models\FiscalPeriod;

final readonly class FiscalPeriodReopened
{
    public function __construct(public FiscalPeriod $period, public string $actorId, public string $reason) {}
}
