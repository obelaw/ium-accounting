<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\FixedAsset;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Obelaw\Ium\Accounting\Enums\DepreciationMethod;
use Obelaw\Ium\Accounting\Enums\DepreciationScheduleStatus;
use Obelaw\Ium\Accounting\Models\DepreciationSchedule;
use Obelaw\Ium\Accounting\Models\FiscalPeriod;
use Obelaw\Ium\Accounting\Models\FixedAsset;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;

/**
 * Generate Straight-Line or Declining-Balance monthly depreciation schedules in integer minor units.
 *
 * @extends Action<int, Collection<int, DepreciationSchedule>>
 */
final class GenerateDepreciationScheduleAction extends Action
{
    protected function handle(mixed $input, Actor $actor): Collection
    {
        $asset = FixedAsset::lockForUpdate()->findOrFail((int) $input);

        $asset->schedules()
            ->where('status', DepreciationScheduleStatus::PENDING->value)
            ->delete();

        $basis = $asset->depreciableBasis();
        $months = (int) $asset->useful_life_months;

        if ($basis <= 0 || $months <= 0) {
            return $asset->schedules()->get();
        }

        $startDate = Carbon::parse($asset->in_service_date)->startOfMonth();
        $runningAccumulated = 0;

        if ($asset->depreciation_method === DepreciationMethod::DECLINING_BALANCE) {
            // Annual rate in bps (defaults to Double-Declining: 200% / useful_life_years)
            $annualRateBps = $asset->declining_rate_bps ?? (int) round((20000 * 12) / $months);

            for ($seq = 1; $seq <= $months; $seq++) {
                $scheduleDate = $startDate->copy()->addMonthsNoOverflow($seq - 1)->endOfMonth()->format('Y-m-d');
                $currentNbv = (int) $asset->acquisition_cost - $runningAccumulated;
                $maxRemaining = max(0, $currentNbv - (int) $asset->salvage_value);

                if ($seq === $months) {
                    $amount = $maxRemaining;
                } else {
                    $monthlyCalc = (int) round(($currentNbv * $annualRateBps) / (10000 * 12), 0, PHP_ROUND_HALF_UP);
                    $amount = min($maxRemaining, max(0, $monthlyCalc));
                }

                $runningAccumulated += $amount;
                $this->createScheduleLine($asset, $seq, $scheduleDate, $amount, $runningAccumulated);
            }
        } else {
            $monthly = intdiv($basis, $months);

            for ($seq = 1; $seq <= $months; $seq++) {
                $scheduleDate = $startDate->copy()->addMonthsNoOverflow($seq - 1)->endOfMonth()->format('Y-m-d');
                $amount = ($seq === $months) ? ($basis - $runningAccumulated) : $monthly;
                $runningAccumulated += $amount;

                $this->createScheduleLine($asset, $seq, $scheduleDate, $amount, $runningAccumulated);
            }
        }

        return $asset->schedules()->get();
    }

    private function createScheduleLine(FixedAsset $asset, int $seq, string $scheduleDate, int $amount, int $runningAccumulated): void
    {
        $periodId = FiscalPeriod::whereDate('start_date', '<=', $scheduleDate)
            ->whereDate('end_date', '>=', $scheduleDate)
            ->value('id');

        DepreciationSchedule::create([
            'fixed_asset_id' => $asset->id,
            'fiscal_period_id' => $periodId,
            'period_sequence' => $seq,
            'schedule_date' => $scheduleDate,
            'depreciation_amount' => $amount,
            'accumulated_depreciation_after' => $runningAccumulated,
            'net_book_value_after' => (int) $asset->acquisition_cost - $runningAccumulated,
            'status' => DepreciationScheduleStatus::PENDING,
        ]);
    }
}
