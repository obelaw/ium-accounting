<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Gateways;

use Obelaw\Ium\Accounting\Actions\Closing\CloseFiscalYearAction;
use Obelaw\Ium\Accounting\Data\CloseFiscalYearData;
use Obelaw\Ium\Accounting\Models\FiscalYear;

final class ClosingGateway
{
    public function __construct(private readonly CloseFiscalYearAction $closeAction) {}

    public function closeFiscalYear(CloseFiscalYearData $data): FiscalYear
    {
        return $this->closeAction->execute($data);
    }
}
