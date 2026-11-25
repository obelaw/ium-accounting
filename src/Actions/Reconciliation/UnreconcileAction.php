<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\Reconciliation;

use Obelaw\Ium\Accounting\Actions\JournalEntry\ReverseJournalEntryAction;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Enums\ReconciliationStatus;
use Obelaw\Ium\Accounting\Models\JournalEntryLine;
use Obelaw\Ium\Accounting\Models\Reconciliation;
use Obelaw\Ium\Accounting\Support\AuditRecorder;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;

/**
 * Undo a reconciliation, restoring open residual balances and reversing any realized FX entry.
 *
 * @extends Action<array{reconciliation_id: int, reason?: string}, bool>
 */
final class UnreconcileAction extends Action
{
    protected ?string $ability = 'accounting.reconciliation.delete';

    public function __construct(
        private readonly ReverseJournalEntryAction $reverseEntry,
        private readonly AuditRecorder $audit,
    ) {}

    protected function handle(mixed $input, Actor $actor): bool
    {
        $reconciliation = Reconciliation::with('allocations')
            ->lockForUpdate()
            ->findOrFail((int) $input['reconciliation_id']);

        $reason = (string) ($input['reason'] ?? 'Unreconciled');
        $before = $reconciliation->toArray();

        if ($reconciliation->fx_gain_loss_entry_id !== null) {
            $this->reverseEntry->execute([
                'entry_id' => $reconciliation->fx_gain_loss_entry_id,
                'reason' => "Unreconciliation of {$reconciliation->reconciliation_number}: {$reason}",
                'actor_id' => $actor->id,
            ]);
        }

        foreach ($reconciliation->allocations as $alloc) {
            $this->revertLine($alloc->debit_line_id, (int) $alloc->amount, (int) $alloc->amount_functional_debit);
            $this->revertLine($alloc->credit_line_id, (int) $alloc->amount, (int) $alloc->amount_functional_credit);
        }

        $this->audit->record(
            action: AuditAction::UNRECONCILED,
            auditable: $reconciliation,
            before: $before,
            after: null,
            actorId: $actor->id,
            reason: $reason,
            originContext: self::class,
        );

        return (bool) $reconciliation->delete();
    }

    private function revertLine(int $lineId, int $amount, int $functional): void
    {
        $line = JournalEntryLine::lockForUpdate()->findOrFail($lineId);
        $newReconciled = max(0, (int) $line->reconciled_amount - $amount);
        $newReconciledFunc = max(0, (int) $line->reconciled_functional - $functional);

        $status = $newReconciled === 0
            ? ReconciliationStatus::OPEN
            : ($newReconciled < (int) $line->amount ? ReconciliationStatus::PARTIAL : ReconciliationStatus::RECONCILED);

        $line->update([
            'reconciled_amount' => $newReconciled,
            'reconciled_functional' => $newReconciledFunc,
            'reconciliation_status' => $status,
        ]);
    }
}
