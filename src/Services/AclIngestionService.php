<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Obelaw\Ium\Accounting\Data\AclIngestEventDto;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryDto;
use Obelaw\Ium\Accounting\Data\JournalEntryLineDto;
use Obelaw\Ium\Accounting\Enums\JournalEntryType;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Exceptions\AccountNotFoundException;
use Obelaw\Ium\Accounting\Models\EventAccountMapping;
use Obelaw\Ium\Accounting\Models\JournalEntry;

class AclIngestionService
{
    public function __construct(
        private readonly ?AccountService $accounts = null,
        private readonly ?JournalEntryService $journalEntries = null
    ) {}

    private function accountService(): AccountService
    {
        return $this->accounts ?? app(AccountService::class);
    }

    private function journalEntryService(): JournalEntryService
    {
        return $this->journalEntries ?? app(JournalEntryService::class);
    }

    /**
     * Register a mapping rule for an operational domain event.
     */
    public function registerMapping(
        string $eventName,
        string $debitAccountCode,
        string $creditAccountCode,
        ?string $descriptionTemplate = null
    ): EventAccountMapping {
        return EventAccountMapping::updateOrCreate(
            ['event_name' => $eventName],
            [
                'debit_account_code' => $debitAccountCode,
                'credit_account_code' => $creditAccountCode,
                'description_template' => $descriptionTemplate,
                'is_active' => true,
            ]
        );
    }

    /**
     * Ingest an operational business event crossing the Anti-Corruption Layer (ACL).
     */
    public function ingest(AclIngestEventDto $dto): JournalEntry
    {
        return DB::transaction(function () use ($dto) {
            $debitCode = $dto->debitAccountCode;
            $creditCode = $dto->creditAccountCode;

            if ($debitCode === null || $creditCode === null) {
                $mapping = EventAccountMapping::active()->where('event_name', $dto->eventName)->first();
                if (!$mapping) {
                    throw new InvalidArgumentException(
                        sprintf('No account mapping found for event "%s". Specify debit and credit account codes.', $dto->eventName)
                    );
                }

                $debitCode ??= $mapping->debit_account_code;
                $creditCode ??= $mapping->credit_account_code;
            }

            $debitAccount = $this->accountService()->findByCode($debitCode);
            if (!$debitAccount) {
                throw AccountNotFoundException::forCode($debitCode);
            }

            $creditAccount = $this->accountService()->findByCode($creditCode);
            if (!$creditAccount) {
                throw AccountNotFoundException::forCode($creditCode);
            }

            $narration = $dto->narration ?? sprintf('Automated posting from [%s]: %s', $dto->eventName, $dto->documentReference);

            $lines = [
                new JournalEntryLineDto(
                    accountId: $debitAccount->id,
                    type: PostingSide::DEBIT,
                    amount: $dto->amount,
                    costCenterId: $dto->costCenterId,
                    lineNumber: 1,
                    narration: $narration
                ),
                new JournalEntryLineDto(
                    accountId: $creditAccount->id,
                    type: PostingSide::CREDIT,
                    amount: $dto->amount,
                    costCenterId: $dto->costCenterId,
                    lineNumber: 2,
                    narration: $narration
                ),
            ];

            $entryDto = new CreateJournalEntryDto(
                entryDate: $dto->transactionDate,
                lines: $lines,
                entryNumber: sprintf('ACL-%s-%s', strtoupper($dto->eventName), $dto->documentReference),
                entryType: JournalEntryType::STANDARD,
                narration: $narration,
                currency: $dto->currency,
                creatorId: $dto->actorId ?? 'acl-dispatcher',
                sourceType: $dto->sourceType,
                sourceId: $dto->sourceId,
                metadata: array_merge($dto->metadata, [
                    'acl_event_name' => $dto->eventName,
                    'acl_document_reference' => $dto->documentReference,
                ])
            );

            $entry = $this->journalEntryService()->createDraft($entryDto);

            if ($dto->autoPost) {
                $entry = $this->journalEntryService()->post($entry->id, $dto->actorId ?? 'acl-dispatcher');
            }

            return $entry;
        });
    }
}
