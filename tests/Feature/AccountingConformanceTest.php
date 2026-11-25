<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Tests\Feature;

use Obelaw\Ium\Accounting\AccountingDomain;
use Obelaw\Ium\Accounting\Data\CreateAccountData;
use Obelaw\Ium\Accounting\Data\CreateJournalEntryData;
use Obelaw\Ium\Accounting\Data\JournalLineData;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\PostingSide;
use Obelaw\Ium\Accounting\Models\Account;
use Obelaw\Ium\Accounting\Providers\ObelawAccountingServiceProvider;
use Obelaw\Ium\Contracts\TenantIdentity;
use Obelaw\Ium\Testing\ModuleTestCase;

final class AccountingConformanceTest extends ModuleTestCase
{
    protected function moduleServiceProviders(): array
    {
        return [
            ObelawAccountingServiceProvider::class,
        ];
    }

    public function test_module_passes_single_and_dual_tenant_conformance(): void
    {
        $this->assertModuleRegistered('accounting', AccountingDomain::class);

        $this->runConformance(function (string $mode, ?TenantIdentity $tenant): void {
            $tag = $tenant !== null ? $tenant->key : $mode;

            $cash = ium()->accounting()->accounts()->create(new CreateAccountData("1010-{$tag}", "Cash {$tag}", AccountType::ASSET));
            $rev = ium()->accounting()->accounts()->create(new CreateAccountData("4010-{$tag}", "Sales {$tag}", AccountType::REVENUE));

            $entry = ium()->accounting()->journalEntries()->createDraft(new CreateJournalEntryData(
                entryDate: '2026-05-01',
                narration: "Conformance {$tag}",
                creatorId: "maker-{$tag}",
                lines: [
                    new JournalLineData($cash->id, PostingSide::DEBIT, 25000),
                    new JournalLineData($rev->id, PostingSide::CREDIT, 25000),
                ],
            ));

            ium()->accounting()->journalEntries()->submit($entry->id, "maker-{$tag}");
            ium()->accounting()->journalEntries()->approve($entry->id, "checker-{$tag}");
            $posted = ium()->accounting()->journalEntries()->post($entry->id, "checker-{$tag}");

            $this->assertTrue($posted->isPosted());

            $tb = ium()->accounting()->reports()->trialBalance();
            $this->assertTrue($tb->isBalanced);
            $this->assertSame(25000, $tb->totalEndingDebit);
            $this->assertSame(25000, $tb->totalEndingCredit);
        });
    }

    public function test_data_sequences_and_settings_are_strictly_isolated_across_tenants(): void
    {
        $n = 0;

        $this->assertDataIsolated(
            writeRecord: function (TenantIdentity $tenant) use (&$n): void {
                $n++;
                ium()->accounting()->accounts()->create(new CreateAccountData("10{$n}-{$tenant->id}", "Account {$n}", AccountType::ASSET));
            },
            countRecords: static fn (): int => Account::count(),
        );

        $this->assertSequenceIsolated('accounting.journal.sj.2026', 'SJ-2026');
        $this->assertCacheAndSettingsIsolated('ium.accounting.conformance_probe');
    }
}
