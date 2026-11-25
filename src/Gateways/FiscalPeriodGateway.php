<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Gateways;

use Carbon\Carbon;
use Obelaw\Ium\Accounting\Actions\Fiscal\CreateFiscalPeriodAction;
use Obelaw\Ium\Accounting\Actions\Fiscal\CreateFiscalYearAction;
use Obelaw\Ium\Accounting\Actions\Fiscal\TransitionFiscalPeriodAction;
use Obelaw\Ium\Accounting\Data\CreateFiscalPeriodData;
use Obelaw\Ium\Accounting\Data\CreateFiscalYearData;
use Obelaw\Ium\Accounting\Enums\FiscalPeriodStatus;
use Obelaw\Ium\Accounting\Exceptions\FiscalPeriodClosedException;
use Obelaw\Ium\Accounting\Models\FiscalPeriod;
use Obelaw\Ium\Accounting\Models\FiscalYear;

final class FiscalPeriodGateway
{
    public function __construct(
        private readonly CreateFiscalYearAction $createYearAction,
        private readonly CreateFiscalPeriodAction $createPeriodAction,
        private readonly TransitionFiscalPeriodAction $transitionAction,
    ) {}

    public function createYear(CreateFiscalYearData $data): FiscalYear
    {
        return $this->createYearAction->execute($data);
    }

    public function createPeriod(CreateFiscalPeriodData $data): FiscalPeriod
    {
        return $this->createPeriodAction->execute($data);
    }

    public function findPeriod(int $id): ?FiscalPeriod
    {
        return FiscalPeriod::find($id);
    }

    public function findPeriodForDate(string $date): ?FiscalPeriod
    {
        $d = Carbon::parse($date)->format('Y-m-d');

        return FiscalPeriod::whereDate('start_date', '<=', $d)
            ->whereDate('end_date', '>=', $d)
            ->first();
    }

    public function softClose(int $periodId, string $reason, ?string $actorId = null): FiscalPeriod
    {
        return $this->transitionAction->execute([
            'period_id' => $periodId,
            'status' => FiscalPeriodStatus::SOFT_CLOSED,
            'reason' => $reason,
            'actor_id' => $actorId,
        ]);
    }

    public function lock(int $periodId, string $reason, ?string $actorId = null): FiscalPeriod
    {
        return $this->transitionAction->execute([
            'period_id' => $periodId,
            'status' => FiscalPeriodStatus::LOCKED,
            'reason' => $reason,
            'actor_id' => $actorId,
        ]);
    }

    public function reopen(int $periodId, string $reason, ?string $actorId = null): FiscalPeriod
    {
        return $this->transitionAction->execute([
            'period_id' => $periodId,
            'status' => FiscalPeriodStatus::OPEN,
            'reason' => $reason,
            'actor_id' => $actorId,
        ]);
    }

    public function assertAllowsPosting(FiscalPeriod $period, bool $isPrivileged = false): void
    {
        if (! $period->status->allowsPosting($isPrivileged)) {
            throw FiscalPeriodClosedException::forPeriod($period->name, $period->status->value);
        }
    }
}
