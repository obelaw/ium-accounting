<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\Fiscal;

use Obelaw\Ium\Accounting\Data\CreateFiscalPeriodData;
use Obelaw\Ium\Accounting\Enums\FiscalPeriodStatus;
use Obelaw\Ium\Accounting\Models\FiscalPeriod;
use Obelaw\Ium\Accounting\Models\FiscalYear;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;

/**
 * @extends Action<CreateFiscalPeriodData, FiscalPeriod>
 */
final class CreateFiscalPeriodAction extends Action
{
    protected ?string $ability = 'accounting.fiscal_period.create';

    protected function handle(mixed $input, Actor $actor): FiscalPeriod
    {
        $year = FiscalYear::findOrFail($input->fiscalYearId);

        return FiscalPeriod::create([
            'fiscal_year_id' => $year->id,
            'period_number' => $input->periodNumber,
            'name' => $input->name,
            'start_date' => $input->startDate,
            'end_date' => $input->endDate,
            'status' => FiscalPeriodStatus::OPEN,
        ]);
    }
}
