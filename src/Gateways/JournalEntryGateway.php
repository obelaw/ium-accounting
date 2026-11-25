<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Gateways;

use Obelaw\Ium\Accounting\Actions\JournalEntry\ApproveJournalEntryAction;
use Obelaw\Ium\Accounting\Actions\JournalEntry\CreateDraftEntryAction;
use Obelaw\Ium\Accounting\Actions\JournalEntry\DeleteDraftEntryAction;
use Obelaw\Ium\Accounting\Actions\JournalEntry\PostJournalEntryAction;
use Obelaw\Ium\Accounting\Actions\JournalEntry\RejectJournalEntryAction;
use Obelaw\Ium\Accounting\Actions\JournalEntry\ReverseJournalEntryAction;
use Obelaw\Ium\Accounting\Actions\JournalEntry\SubmitJournalEntryAction;
use Obelaw\Ium\Accounting\Actions\JournalEntry\UpdateDraftEntryAction;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryData;
use Obelaw\Ium\Accounting\Data\UpdateJournalEntryData;
use Obelaw\Ium\Accounting\Models\JournalEntry;

final class JournalEntryGateway
{
    public function __construct(
        private readonly CreateDraftEntryAction $createDraftAction,
        private readonly UpdateDraftEntryAction $updateDraftAction,
        private readonly DeleteDraftEntryAction $deleteDraftAction,
        private readonly SubmitJournalEntryAction $submitAction,
        private readonly ApproveJournalEntryAction $approveAction,
        private readonly RejectJournalEntryAction $rejectAction,
        private readonly PostJournalEntryAction $postAction,
        private readonly ReverseJournalEntryAction $reverseAction,
    ) {}

    public function createDraft(CreateJournalEntryData $data): JournalEntry
    {
        return $this->createDraftAction->execute($data);
    }

    public function updateDraft(UpdateJournalEntryData $data): JournalEntry
    {
        return $this->updateDraftAction->execute($data);
    }

    public function deleteDraft(int $entryId): bool
    {
        return $this->deleteDraftAction->execute($entryId);
    }

    public function submit(int $entryId, ?string $actorId = null): JournalEntry
    {
        return $this->submitAction->execute(['entry_id' => $entryId, 'actor_id' => $actorId]);
    }

    public function approve(int $entryId, ?string $approverId = null): JournalEntry
    {
        return $this->approveAction->execute(['entry_id' => $entryId, 'approver_id' => $approverId]);
    }

    public function reject(int $entryId, string $reason, ?string $actorId = null): JournalEntry
    {
        return $this->rejectAction->execute(['entry_id' => $entryId, 'reason' => $reason, 'actor_id' => $actorId]);
    }

    public function post(int $entryId, ?string $actorId = null, bool $bypassApproval = false, bool $isPrivileged = false): JournalEntry
    {
        return $this->postAction->execute([
            'entry_id' => $entryId,
            'actor_id' => $actorId,
            'bypass_approval' => $bypassApproval,
            'is_privileged' => $isPrivileged,
        ]);
    }

    public function reverse(int $entryId, string $reason, ?string $actorId = null, ?string $reversalDate = null, ?int $fiscalPeriodId = null): JournalEntry
    {
        return $this->reverseAction->execute([
            'entry_id' => $entryId,
            'reason' => $reason,
            'actor_id' => $actorId,
            'reversal_date' => $reversalDate,
            'fiscal_period_id' => $fiscalPeriodId,
        ]);
    }

    public function find(int $id): ?JournalEntry
    {
        return JournalEntry::with(['lines.account', 'journal'])->find($id);
    }

    public function findByNumber(string $entryNumber): ?JournalEntry
    {
        return JournalEntry::with(['lines.account', 'journal'])->where('entry_number', $entryNumber)->first();
    }
}
