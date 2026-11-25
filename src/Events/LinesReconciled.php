<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Events;

use Obelaw\Ium\Accounting\Models\Reconciliation;

final readonly class LinesReconciled
{
    public function __construct(public Reconciliation $reconciliation, public string $actorId) {}
}
