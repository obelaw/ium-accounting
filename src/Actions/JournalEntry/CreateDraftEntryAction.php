<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\JournalEntry;

use Carbon\Carbon;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryData;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Enums\JournalEntryStatus;
use Obelaw\Ium\Accounting\Enums\ReconciliationStatus;
use Obelaw\Ium\Accounting\Events\JournalEntryCreated;
use Obelaw\Ium\Accounting\Models\FiscalPeriod;
use Obelaw\Ium\Accounting\Models\Journal;
use Obelaw\Ium\Accounting\Models\JournalEntry;
use Obelaw\Ium\Accounting\Models\JournalEntryLine;
use Obelaw\Ium\Accounting\Support\AuditRecorder;
use Obelaw\Ium\Accounting\Support\ExchangeRateResolver;
use Obelaw\Ium\Accounting\Support\JournalLineBuilder;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;
use Obelaw\Ium\Facades\Ium;

/**
 * @extends Action<CreateJournalEntryData, JournalEntry>
 */
final class CreateDraftEntryAction extends Action
{
    protected ?string $ability = 'accounting.journal_entry.create';

    public function __construct(
        private readonly JournalLineBuilder $lineBuilder,
        private readonly ExchangeRateResolver $fxResolver,
        private readonly AuditRecorder $audit,
    ) {}

    protected function handle(mixed $input, Actor $actor): JournalEntry
    {
        // Idempotent creation when idempotencyKey is provided
        if ($input->idempotencyKey !== null && trim($input->idempotencyKey) !== '') {
            $existing = JournalEntry::where('idempotency_key', $input->idempotencyKey)->first();
            if ($existing !== null) {
                return $existing->fresh(['lines.account', 'journal']);
            }
        }

        $journal = $this->resolveJournal($input);
        $periodId = $input->fiscalPeriodId ?? $this->resolvePeriodId($input->entryDate);
        $sums = $this->lineBuilder->validateAndSum($input->lines);

        $baseCurrency = $this->fxResolver->baseCurrencyCode();
        $exchangeRate = $this->fxResolver->resolveRateForEntry($input->currency, $input->entryDate, $input->exchangeRate);
        $functionalAmounts = $this->lineBuilder->allocateFunctional(
            $input->lines,
            $exchangeRate,
            $input->currency,
            $baseCurrency,
        );

        $prefix = $journal?->sequence_prefix ?? 'JV';
        $year = Carbon::parse($input->entryDate)->format('Y');
        $entryNumber = $input->entryNumber ?? Ium::sequence()->next(
            key: sprintf('accounting.journal.%s.%s', strtolower($prefix), $year),
            prefix: "{$prefix}-{$year}",
            padding: 6,
        );

        $creatorId = $input->creatorId ?? $actor->id;

        $entry = JournalEntry::create([
            'entry_number' => $entryNumber,
            'journal_id' => $journal?->id,
            'entry_date' => $input->entryDate,
            'fiscal_period_id' => $periodId,
            'status' => JournalEntryStatus::DRAFT,
            'entry_type' => $input->entryType,
            'reference' => $input->reference,
            'narration' => $input->narration,
            'currency' => $input->currency,
            'exchange_rate' => $exchangeRate,
            'total_debit' => $sums['total_debit'],
            'total_credit' => $sums['total_credit'],
            'creator_id' => $creatorId,
            'idempotency_key' => $input->idempotencyKey,
            'source_type' => $input->sourceType,
            'source_id' => $input->sourceId,
            'metadata' => $input->metadata,
        ]);

        $lineNumber = 1;
        foreach ($input->lines as $idx => $line) {
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $line->accountId,
                'cost_center_id' => $line->costCenterId,
                'tax_id' => $line->taxId,
                'line_number' => $line->lineNumber ?? $lineNumber++,
                'type' => $line->type,
                'amount' => $line->amount,
                'amount_functional' => $functionalAmounts[$idx],
                'due_date' => $line->dueDate,
                'narration' => $line->narration ?? $input->narration,
                'party_type' => $line->partyType,
                'party_id' => $line->partyId,
                'reconciled_amount' => 0,
                'reconciled_functional' => 0,
                'reconciliation_status' => ReconciliationStatus::NONE,
                'metadata' => $line->metadata,
            ]);
        }

        $fresh = $entry->fresh(['lines.account', 'journal']);

        $this->audit->record(
            action: AuditAction::CREATED,
            auditable: $fresh,
            after: $fresh->toArray(),
            actorId: $creatorId,
            originContext: self::class,
        );

        $this->recordEvent(new JournalEntryCreated($fresh, $creatorId));

        return $fresh;
    }

    private function resolveJournal(CreateJournalEntryData $input): ?Journal
    {
        if ($input->journalId !== null) {
            return Journal::findOrFail($input->journalId);
        }

        if ($input->journalCode !== null) {
            return Journal::where('code', strtoupper(trim($input->journalCode)))->firstOrFail();
        }

        return null;
    }

    private function resolvePeriodId(string $entryDate): ?int
    {
        return FiscalPeriod::whereDate('start_date', '<=', $entryDate)
            ->whereDate('end_date', '>=', $entryDate)
            ->value('id');
    }
}
