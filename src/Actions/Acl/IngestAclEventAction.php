<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\Acl;

use InvalidArgumentException;
use Obelaw\Ium\Accounting\Actions\JournalEntry\CreateDraftEntryAction;
use Obelaw\Ium\Accounting\Actions\JournalEntry\PostJournalEntryAction;
use Obelaw\Ium\Accounting\Data\AclIngestData;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryData;
use Obelaw\Ium\Accounting\Data\JournalLineData;
use Obelaw\Ium\Accounting\Enums\JournalEntryType;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Exceptions\AccountNotFoundException;
use Obelaw\Ium\Accounting\Models\Account;
use Obelaw\Ium\Accounting\Models\JournalEntry;
use Obelaw\Ium\Accounting\Models\PostingRule;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;

/**
 * Anti-Corruption Layer (ACL) Ingestion Action.
 *
 * Translates operational domain events (Sales, Purchase, Inventory, E-Commerce)
 * into balanced, idempotent, multi-line General Ledger vouchers.
 *
 * @extends Action<AclIngestData, JournalEntry>
 */
final class IngestAclEventAction extends Action
{
    protected ?string $ability = 'accounting.acl.ingest';

    public function __construct(
        private readonly CreateDraftEntryAction $createDraft,
        private readonly PostJournalEntryAction $postEntry,
    ) {}

    protected function handle(mixed $input, Actor $actor): JournalEntry
    {
        if ($input->idempotencyKey !== null && trim($input->idempotencyKey) !== '') {
            $existing = JournalEntry::where('idempotency_key', $input->idempotencyKey)->first();
            if ($existing !== null) {
                return $existing->fresh(['lines.account', 'journal']);
            }
        }

        $narration = $input->narration ?? sprintf('ACL [%s]: %s', $input->eventName, $input->documentReference);
        [$lines, $ruleJournalId] = $this->resolveLines($input, $narration);

        $draft = $this->createDraft->execute(new CreateJournalEntryData(
            entryDate: $input->transactionDate,
            lines: $lines,
            journalId: $input->journalCode === null ? $ruleJournalId : null,
            journalCode: $input->journalCode,
            entryType: JournalEntryType::STANDARD,
            reference: $input->documentReference,
            narration: $narration,
            currency: $input->currency,
            creatorId: $actor->id,
            idempotencyKey: $input->idempotencyKey,
            sourceType: $input->sourceType,
            sourceId: $input->sourceId,
            metadata: array_merge($input->metadata, [
                'acl_event_name' => $input->eventName,
                'acl_document_reference' => $input->documentReference,
            ]),
        ));

        if ($input->autoPost) {
            return $this->postEntry->execute([
                'entry_id' => $draft->id,
                'actor_id' => $actor->id,
                'bypass_approval' => true,
            ]);
        }

        return $draft;
    }

    /**
     * @return array{0: list<JournalLineData>, 1: ?int}
     */
    private function resolveLines(AclIngestData $input, string $defaultNarration): array
    {
        if ($input->lines !== []) {
            $built = [];
            $idx = 1;

            foreach ($input->lines as $raw) {
                $code = (string) ($raw['account_code'] ?? throw new InvalidArgumentException('Each ACL line requires account_code.'));
                $account = Account::where('code', $code)->first() ?? throw AccountNotFoundException::forCode($code);

                $side = $raw['type'] instanceof PostingSide
                    ? $raw['type']
                    : PostingSide::from(strtolower((string) $raw['type']));

                $built[] = new JournalLineData(
                    accountId: $account->id,
                    type: $side,
                    amount: (int) $raw['amount'],
                    costCenterId: isset($raw['cost_center_id']) ? (int) $raw['cost_center_id'] : $input->costCenterId,
                    taxId: isset($raw['tax_id']) ? (int) $raw['tax_id'] : $input->taxId,
                    dueDate: isset($raw['due_date']) ? (string) $raw['due_date'] : $input->dueDate,
                    narration: isset($raw['narration']) ? (string) $raw['narration'] : $defaultNarration,
                    partyType: isset($raw['party_type']) ? (string) $raw['party_type'] : $input->partyType,
                    partyId: isset($raw['party_id']) ? (int) $raw['party_id'] : $input->partyId,
                    lineNumber: $idx++,
                );
            }

            return [$built, null];
        }

        $debitCode = $input->debitAccountCode;
        $creditCode = $input->creditAccountCode;
        $ruleJournalId = null;

        if ($debitCode === null || $creditCode === null) {
            $rule = PostingRule::active()->where('event_name', $input->eventName)->first();
            if ($rule === null) {
                throw new InvalidArgumentException("No active posting rule found for event [{$input->eventName}].");
            }

            $debitCode ??= $rule->debit_account_code;
            $creditCode ??= $rule->credit_account_code;
            $ruleJournalId = $rule->journal_id;
        }

        $debitAccount = Account::where('code', $debitCode)->first() ?? throw AccountNotFoundException::forCode($debitCode);
        $creditAccount = Account::where('code', $creditCode)->first() ?? throw AccountNotFoundException::forCode($creditCode);

        return [[
            new JournalLineData(
                accountId: $debitAccount->id,
                type: PostingSide::DEBIT,
                amount: $input->amount,
                costCenterId: $input->costCenterId,
                taxId: $input->taxId,
                dueDate: $input->dueDate,
                narration: $defaultNarration,
                partyType: $input->partyType,
                partyId: $input->partyId,
                lineNumber: 1,
            ),
            new JournalLineData(
                accountId: $creditAccount->id,
                type: PostingSide::CREDIT,
                amount: $input->amount,
                costCenterId: $input->costCenterId,
                taxId: $input->taxId,
                dueDate: $input->dueDate,
                narration: $defaultNarration,
                partyType: $input->partyType,
                partyId: $input->partyId,
                lineNumber: 2,
            ),
        ], $ruleJournalId];
    }
}
