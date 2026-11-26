<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryData;
use Obelaw\Ium\Accounting\Data\JournalLineData;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Facades\Ium;
use Obelaw\Ium\Mcp\Base\IumTool;

#[Name('accounting.create-draft-journal-entry')]
#[Title('Create Draft Journal Entry')]
#[Description('Creates a balanced double-entry Draft Journal Entry via ium()->accounting()->journalEntries()->createDraft(). Enforces Zero-Sum balance and Maker-Checker governance (Draft only, never auto-posts).')]
#[IsDestructive(false)]
final class CreateDraftJournalEntryTool extends IumTool
{
    public function module(): ?string
    {
        return 'accounting';
    }

    public function isWriteAction(): bool
    {
        return true;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'entry_date' => $schema->string()->description('Entry date in YYYY-MM-DD format.')->required(),
            'journal_code' => $schema->string()->description('Optional journal code (e.g. GJ, SJ, PJ).'),
            'journal_id' => $schema->integer()->description('Optional journal ID.'),
            'currency' => $schema->string()->description('3-letter ISO currency code (default USD).')->default('USD'),
            'reference' => $schema->string()->description('Optional external document reference.'),
            'narration' => $schema->string()->description('Description / memo explaining the journal entry.'),
            'lines' => $schema->array()->items(
                $schema->object([
                    'account_id' => $schema->integer()->description('Posting account ID (resolve via accounting.search-accounts first).')->required(),
                    'side' => $schema->string()->enum(['debit', 'credit'])->description('Posting side: debit or credit.')->required(),
                    'amount' => $schema->number()->description('Positive monetary amount in major units (e.g. 1500.50).')->required(),
                    'narration' => $schema->string()->description('Optional line-level description.'),
                ])
            )->description('At least two balanced lines where sum(debit) === sum(credit).')->required(),
        ];
    }

    protected function execute(Request $request): array
    {
        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'journal_code' => ['nullable', 'string', 'max:32'],
            'journal_id' => ['nullable', 'integer'],
            'currency' => ['nullable', 'string', 'size:3'],
            'reference' => ['nullable', 'string', 'max:191'],
            'narration' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer'],
            'lines.*.side' => ['required', 'in:debit,credit'],
            'lines.*.amount' => ['required', 'numeric', 'gt:0'],
            'lines.*.narration' => ['nullable', 'string', 'max:500'],
        ]);

        $currency = strtoupper((string) ($validated['currency'] ?? 'USD'));

        $lines = array_map(
            static fn (array $line): JournalLineData => JournalLineData::fromMajor(
                accountId: (int) $line['account_id'],
                type: $line['side'] === 'debit' ? PostingSide::DEBIT : PostingSide::CREDIT,
                amountMajor: (string) $line['amount'],
                currency: $currency,
                narration: isset($line['narration']) ? (string) $line['narration'] : null,
            ),
            $validated['lines'],
        );

        $entry = ium()->accounting()->journalEntries()->createDraft(new CreateJournalEntryData(
            entryDate: (string) $validated['entry_date'],
            lines: $lines,
            journalId: isset($validated['journal_id']) ? (int) $validated['journal_id'] : null,
            journalCode: $validated['journal_code'] ?? null,
            reference: $validated['reference'] ?? null,
            narration: $validated['narration'] ?? null,
            currency: $currency,
            creatorId: Ium::actor()->id,
        ));

        return [
            'id' => $entry->id,
            'entry_number' => $entry->entry_number,
            'entry_date' => (string) $entry->entry_date,
            'status' => $entry->status instanceof \BackedEnum ? $entry->status->value : (string) $entry->status,
            'currency' => $entry->currency,
            'total_debit_minor' => (int) $entry->total_debit,
            'total_credit_minor' => (int) $entry->total_credit,
            'lines_count' => $entry->lines()->count(),
            'message' => "Draft Journal Entry [{$entry->entry_number}] created and awaiting review/posting.",
            '_resource_type' => 'journal_entry',
            '_resource_id' => $entry->id,
        ];
    }
}
