<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Events;

use Obelaw\Ium\Accounting\Models\Currency;

final readonly class BaseCurrencyChanged
{
    public function __construct(public ?Currency $previousBase, public Currency $newBase, public ?string $actorId = null) {}
}
