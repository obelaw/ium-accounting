<?php

declare(strict_types=1);

use Obelaw\Ium\Accounting\Data\CreateAccountDto;
use Obelaw\Ium\Accounting\Data\CreateCostCenterDto;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryDto;
use Obelaw\Ium\Accounting\Enums\AccountClassification;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\DimensionType;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Models\AccountingAuditLog;
use Obelaw\Ium\Accounting\Services\AccountService;
use Obelaw\Ium\Accounting\Services\AuditService;
use Obelaw\Ium\Accounting\Services\CostCenterService;
use Obelaw\Ium\Accounting\Services\JournalEntryService;

beforeEach(function () {
    $this->accountService = app(AccountService::class);
    $this->journalService = app(JournalEntryService::class);
    $this->costCenterService = app(CostCenterService::class);
    $this->auditService = app(AuditService::class);

    $this->cash = $this->accountService->create(CreateAccountDto::from([
        'code' => '1010',
        'name' => 'Petty Cash',
        'type' => AccountType::ASSET,
        'classification' => AccountClassification::POSTING,
        'is_leaf' => true,
    ]));

    $this->advertising = $this->accountService->create(CreateAccountDto::from([
        'code' => '5020',
        'name' => 'Marketing Expense',
        'type' => AccountType::EXPENSE,
        'classification' => AccountClassification::POSTING,
        'is_leaf' => true,
    ]));
});

test('can create cost centers and attach analytical tags to journal entry lines', function () {
    $dept = $this->costCenterService->create(CreateCostCenterDto::from([
        'code' => 'MKT',
        'name' => 'Marketing Department',
        'dimension_type' => DimensionType::DEPARTMENT,
    ]));

    $campaign = $this->costCenterService->create(CreateCostCenterDto::from([
        'code' => 'CAMP-Q1',
        'name' => 'Q1 Growth Campaign',
        'dimension_type' => DimensionType::PROJECT,
        'parent_id' => $dept->id,
    ]));

    expect($campaign->parent_id)->toBe($dept->id);

    // Journal Entry with Cost Center tag
    $entry = $this->journalService->createDraft(CreateJournalEntryDto::from([
        'entry_date' => '2026-09-01',
        'lines' => [
            [
                'account_id' => $this->advertising->id,
                'type' => PostingSide::DEBIT,
                'amount' => 1200.0,
                'cost_center_id' => $campaign->id,
            ],
            [
                'account_id' => $this->cash->id,
                'type' => PostingSide::CREDIT,
                'amount' => 1200.0,
            ],
        ],
    ]));

    $this->journalService->post($entry->id, 'media-buyer-1');

    $line = $entry->lines->first();
    expect($line->cost_center_id)->toBe($campaign->id)
        ->and($line->costCenter->code)->toBe('CAMP-Q1');
});

test('forensic audit log preserves immutable history of accounting actions', function () {
    $entry = $this->journalService->createDraft(CreateJournalEntryDto::from([
        'entry_date' => '2026-09-15',
        'creator_id' => 'maker-agent',
        'lines' => [
            ['account_id' => $this->advertising->id, 'type' => PostingSide::DEBIT, 'amount' => 750.0],
            ['account_id' => $this->cash->id, 'type' => PostingSide::CREDIT, 'amount' => 750.0],
        ],
    ]));

    $this->journalService->submit($entry->id, 'maker-agent');
    $this->journalService->approve($entry->id, 'checker-agent');
    $this->journalService->post($entry->id, 'poster-agent');

    $history = $this->auditService->historyFor($entry);

    expect($history)->not->toBeEmpty();
    $actions = $history->pluck('action.value')->all();

    expect($actions)->toContain('created')
        ->and($actions)->toContain('submitted')
        ->and($actions)->toContain('approved')
        ->and($actions)->toContain('posted');

    // Audit logs are append-only and cannot be mutated or deleted
    $firstLog = $history->first();
    expect(fn() => $firstLog->update(['reason' => 'Tampered']))->toThrow(\Obelaw\Ium\Accounting\Exceptions\ImmutableJournalEntryException::class);
    expect(fn() => $firstLog->delete())->toThrow(\Obelaw\Ium\Accounting\Exceptions\ImmutableJournalEntryException::class);
});

test('cost center service enforces unique codes and supports filtering by dimension type', function () {
    $cc1 = $this->costCenterService->create(CreateCostCenterDto::from([
        'code' => 'ENG-01',
        'name' => 'Core Engineering',
        'dimension_type' => DimensionType::DEPARTMENT,
    ]));

    $cc2 = $this->costCenterService->create(CreateCostCenterDto::from([
        'code' => 'PRJ-99',
        'name' => 'ERP Migration Project',
        'dimension_type' => DimensionType::PROJECT,
    ]));

    expect(fn() => $this->costCenterService->create(CreateCostCenterDto::from([
        'code' => 'ENG-01',
        'name' => 'Duplicate Dept',
        'dimension_type' => DimensionType::DEPARTMENT,
    ])))->toThrow(InvalidArgumentException::class, 'Cost center code "ENG-01" already exists.');

    expect($this->costCenterService->find($cc1->id)?->code)->toBe('ENG-01')
        ->and($this->costCenterService->findByCode('PRJ-99')?->id)->toBe($cc2->id)
        ->and($this->costCenterService->list())->toHaveCount(2)
        ->and($this->costCenterService->list(DimensionType::PROJECT))->toHaveCount(1)
        ->and($this->costCenterService->list(DimensionType::PROJECT)->first()->code)->toBe('PRJ-99');
});

test('audit service skips recording when audit config is disabled', function () {
    config()->set('ium.accounting.audit.enabled', false);

    $log = $this->auditService->record(
        action: \Obelaw\Ium\Accounting\Enums\AuditAction::CREATED,
        auditable: $this->cash,
        actorId: 'test-actor'
    );

    expect($log)->toBeNull();
});
