<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Obelaw\Ium\Accounting\Data\CreateFiscalPeriodDto;
use Obelaw\Ium\Accounting\Data\CreateFiscalYearDto;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Enums\FiscalPeriodStatus;
use Obelaw\Ium\Accounting\Events\FiscalPeriodClosed;
use Obelaw\Ium\Accounting\Events\FiscalPeriodReopened;
use Obelaw\Ium\Accounting\Exceptions\FiscalPeriodClosedException;
use Obelaw\Ium\Accounting\Models\FiscalPeriod;
use Obelaw\Ium\Accounting\Models\FiscalYear;

class FiscalPeriodService
{
    public function __construct(
        private readonly ?AuditService $audit = null
    ) {}

    private function auditService(): AuditService
    {
        return $this->audit ?? app(AuditService::class);
    }

    /**
     * Create a new Fiscal Year.
     */
    public function createYear(CreateFiscalYearDto $dto): FiscalYear
    {
        return DB::transaction(function () use ($dto) {
            return FiscalYear::create([
                'name' => $dto->name,
                'start_date' => $dto->startDate,
                'end_date' => $dto->endDate,
                'status' => $dto->status,
            ]);
        });
    }

    /**
     * Create a new Fiscal Period under a Fiscal Year.
     */
    public function createPeriod(CreateFiscalPeriodDto $dto): FiscalPeriod
    {
        return DB::transaction(function () use ($dto) {
            $year = FiscalYear::findOrFail($dto->fiscalYearId);

            return FiscalPeriod::create([
                'fiscal_year_id' => $year->id,
                'period_number' => $dto->periodNumber,
                'name' => $dto->name,
                'start_date' => $dto->startDate,
                'end_date' => $dto->endDate,
                'status' => $dto->status,
            ]);
        });
    }

    /**
     * Resolve the active fiscal period for a given calendar date.
     */
    public function findPeriodForDate(string $date): ?FiscalPeriod
    {
        $d = Carbon::parse($date)->format('Y-m-d');

        return FiscalPeriod::where('start_date', '<=', $d)
            ->where('end_date', '>=', $d)
            ->first();
    }

    public function findPeriod(int $id): ?FiscalPeriod
    {
        return FiscalPeriod::find($id);
    }

    /**
     * Soft-close a period (restricts postings to privileged accountants).
     */
    public function softClosePeriod(int $periodId, string $actorId, string $reason): FiscalPeriod
    {
        return DB::transaction(function () use ($periodId, $actorId, $reason) {
            $period = FiscalPeriod::findOrFail($periodId);
            $before = $period->toArray();

            $period->update([
                'status' => FiscalPeriodStatus::SOFT_CLOSED,
                'lock_reason' => $reason,
            ]);

            $this->auditService()->record(
                action: AuditAction::PERIOD_LOCKED,
                auditable: $period,
                before: $before,
                after: $period->fresh()->toArray(),
                actorId: $actorId,
                reason: $reason,
                originContext: 'FiscalPeriodService::softClosePeriod'
            );

            event(new FiscalPeriodClosed($period, $actorId, $reason));

            return $period;
        });
    }

    /**
     * Hard-lock a period (immutable seal, absolutely zero mutations permitted).
     */
    public function lockPeriod(int $periodId, string $actorId, string $reason): FiscalPeriod
    {
        return DB::transaction(function () use ($periodId, $actorId, $reason) {
            $period = FiscalPeriod::findOrFail($periodId);
            $before = $period->toArray();

            $period->update([
                'status' => FiscalPeriodStatus::LOCKED,
                'locked_at' => now(),
                'locked_by' => $actorId,
                'lock_reason' => $reason,
            ]);

            $this->auditService()->record(
                action: AuditAction::PERIOD_LOCKED,
                auditable: $period,
                before: $before,
                after: $period->fresh()->toArray(),
                actorId: $actorId,
                reason: $reason,
                originContext: 'FiscalPeriodService::lockPeriod'
            );

            event(new FiscalPeriodClosed($period, $actorId, $reason));

            return $period;
        });
    }

    /**
     * Reopen a soft-closed or locked period with a mandatory administrative reason.
     */
    public function reopenPeriod(int $periodId, string $actorId, string $reason): FiscalPeriod
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Reopening a closed fiscal period requires a mandatory reason.');
        }

        return DB::transaction(function () use ($periodId, $actorId, $reason) {
            $period = FiscalPeriod::findOrFail($periodId);
            $before = $period->toArray();

            $period->update([
                'status' => FiscalPeriodStatus::OPEN,
                'locked_at' => null,
                'locked_by' => null,
                'lock_reason' => sprintf('Reopened by %s: %s', $actorId, $reason),
            ]);

            $this->auditService()->record(
                action: AuditAction::PERIOD_REOPENED,
                auditable: $period,
                before: $before,
                after: $period->fresh()->toArray(),
                actorId: $actorId,
                reason: $reason,
                originContext: 'FiscalPeriodService::reopenPeriod'
            );

            event(new FiscalPeriodReopened($period, $actorId, $reason));

            return $period;
        });
    }

    /**
     * Assert that postings are allowed for the target period.
     */
    public function assertPeriodAllowsPosting(FiscalPeriod $period, bool $isPrivileged = false): void
    {
        if (!$period->status->allowsPosting($isPrivileged)) {
            throw FiscalPeriodClosedException::forPeriod($period->name, $period->status->value);
        }
    }
}
