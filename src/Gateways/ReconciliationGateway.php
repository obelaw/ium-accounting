<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Gateways;

use Illuminate\Database\Eloquent\Collection;
use Obelaw\Ium\Accounting\Actions\Reconciliation\ReconcileLinesAction;
use Obelaw\Ium\Accounting\Actions\Reconciliation\UnreconcileAction;
use Obelaw\Ium\Accounting\Data\ReconcileLinesData;
use Obelaw\Ium\Accounting\Enums\JournalEntryStatus;
use Obelaw\Ium\Accounting\Enums\ReconciliationStatus;
use Obelaw\Ium\Accounting\Models\JournalEntryLine;
use Obelaw\Ium\Accounting\Models\Reconciliation;

final class ReconciliationGateway
{
    public function __construct(
        private readonly ReconcileLinesAction $reconcileAction,
        private readonly UnreconcileAction $unreconcileAction,
    ) {}

    public function reconcile(ReconcileLinesData $data): Reconciliation
    {
        return $this->reconcileAction->execute($data);
    }

    public function unreconcile(int $reconciliationId, string $reason = 'Unreconciled'): bool
    {
        return $this->unreconcileAction->execute([
            'reconciliation_id' => $reconciliationId,
            'reason' => $reason,
        ]);
    }

    /**
     * Retrieve open / partially reconciled lines on a reconcilable account.
     *
     * @return Collection<int, JournalEntryLine>
     */
    public function openItems(int $accountId, ?string $partyType = null, ?int $partyId = null): Collection
    {
        $query = JournalEntryLine::with('journalEntry')
            ->where('account_id', $accountId)
            ->whereIn('reconciliation_status', [ReconciliationStatus::OPEN->value, ReconciliationStatus::PARTIAL->value])
            ->whereHas('journalEntry', static fn ($q) => $q->where('status', JournalEntryStatus::POSTED->value))
            ->orderBy('id');

        if ($partyType !== null) {
            $query->where('party_type', $partyType);
        }
        if ($partyId !== null) {
            $query->where('party_id', $partyId);
        }

        return $query->get();
    }
}
