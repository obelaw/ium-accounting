<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\Fiscal;

use InvalidArgumentException;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Enums\FiscalPeriodStatus;
use Obelaw\Ium\Accounting\Events\FiscalPeriodClosed;
use Obelaw\Ium\Accounting\Events\FiscalPeriodReopened;
use Obelaw\Ium\Accounting\Models\FiscalPeriod;
use Obelaw\Ium\Accounting\Support\AuditRecorder;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;

/**
 * @extends Action<array{period_id: int, status: FiscalPeriodStatus, reason: string, actor_id?: ?string}, FiscalPeriod>
 */
final class TransitionFiscalPeriodAction extends Action
{
    protected ?string $ability = 'accounting.fiscal_period.transition';

    public function __construct(private readonly AuditRecorder $audit) {}

    protected function handle(mixed $input, Actor $actor): FiscalPeriod
    {
        $periodId = (int) $input['period_id'];
        /** @var FiscalPeriodStatus $targetStatus */
        $targetStatus = $input['status'];
        $reason = trim((string) ($input['reason'] ?? ''));
        $actorId = (string) ($input['actor_id'] ?? $actor->id);

        if ($targetStatus === FiscalPeriodStatus::OPEN && $reason === '') {
            throw new InvalidArgumentException('Reopening a closed fiscal period requires a mandatory reason.');
        }

        $period = FiscalPeriod::lockForUpdate()->findOrFail($periodId);
        $before = $period->toArray();

        if ($targetStatus === FiscalPeriodStatus::LOCKED) {
            $period->update([
                'status' => FiscalPeriodStatus::LOCKED,
                'locked_at' => now(),
                'locked_by' => $actorId,
                'lock_reason' => $reason,
            ]);
            $auditAction = AuditAction::PERIOD_LOCKED;
            $this->recordEvent(new FiscalPeriodClosed($period, $actorId, $reason));
        } elseif ($targetStatus === FiscalPeriodStatus::SOFT_CLOSED) {
            $period->update([
                'status' => FiscalPeriodStatus::SOFT_CLOSED,
                'lock_reason' => $reason,
            ]);
            $auditAction = AuditAction::PERIOD_LOCKED;
            $this->recordEvent(new FiscalPeriodClosed($period, $actorId, $reason));
        } else {
            $period->update([
                'status' => FiscalPeriodStatus::OPEN,
                'locked_at' => null,
                'locked_by' => null,
                'lock_reason' => "Reopened by {$actorId}: {$reason}",
            ]);
            $auditAction = AuditAction::PERIOD_REOPENED;
            $this->recordEvent(new FiscalPeriodReopened($period, $actorId, $reason));
        }

        $fresh = $period->fresh();

        $this->audit->record(
            action: $auditAction,
            auditable: $fresh,
            before: $before,
            after: $fresh->toArray(),
            actorId: $actorId,
            reason: $reason,
            originContext: self::class,
        );

        return $fresh;
    }
}
