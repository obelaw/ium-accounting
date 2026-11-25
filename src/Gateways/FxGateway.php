<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Gateways;

use Obelaw\Ium\Accounting\Actions\Fx\RevalueOpenForeignBalancesAction;
use Obelaw\Ium\Accounting\Data\FxRevaluationData;
use Obelaw\Ium\Accounting\Reports\FxRevaluationResult;

final class FxGateway
{
    public function __construct(private readonly RevalueOpenForeignBalancesAction $revalueAction) {}

    public function revalue(FxRevaluationData $data): FxRevaluationResult
    {
        return $this->revalueAction->execute($data);
    }
}
