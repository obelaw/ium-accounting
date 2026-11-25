<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\Fiscal;

use Carbon\Carbon;
use Obelaw\Ium\Accounting\Data\CreateFiscalYearData;
use Obelaw\Ium\Accounting\Enums\FiscalPeriodStatus;
use Obelaw\Ium\Accounting\Models\FiscalPeriod;
use Obelaw\Ium\Accounting\Models\FiscalYear;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;

/**
 * @extends Action<CreateFiscalYearData, FiscalYear>
 */
final class CreateFiscalYearAction extends Action
{
    protected ?string $ability = 'accounting.fiscal_year.create';

    protected function handle(mixed $input, Actor $actor): FiscalYear
    {
        $year = FiscalYear::create([
            'name' => $input->name,
            'start_date' => $input->startDate,
            'end_date' => $input->endDate,
            'status' => FiscalPeriodStatus::OPEN,
        ]);

        if ($input->generateMonthlyPeriods) {
            $cursor = Carbon::parse($input->startDate)->startOfMonth();
            $end = Carbon::parse($input->endDate);
            $periodNum = 1;

            while ($cursor->lte($end)) {
                $pStart = $cursor->copy();
                $pEnd = $cursor->copy()->endOfMonth();
                if ($pEnd->gt($end)) {
                    $pEnd = $end->copy();
                }

                FiscalPeriod::create([
                    'fiscal_year_id' => $year->id,
                    'period_number' => $periodNum++,
                    'name' => $pStart->format('F Y'),
                    'start_date' => $pStart->format('Y-m-d'),
                    'end_date' => $pEnd->format('Y-m-d'),
                    'status' => FiscalPeriodStatus::OPEN,
                ]);

                $cursor->addMonthNoOverflow()->startOfMonth();
            }
        }

        return $year->fresh(['periods']);
    }
}
