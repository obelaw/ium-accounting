<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Obelaw\Ium\Accounting\Models\Account;

final class AccountBalanceThresholdBreached
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Account $account,
        public readonly float $currentBalance,
        public readonly float $threshold
    ) {}
}
