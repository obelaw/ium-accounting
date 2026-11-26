<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Mcp;

use Obelaw\Ium\Accounting\Mcp\Tools\CreateDraftJournalEntryTool;
use Obelaw\Ium\Accounting\Mcp\Tools\GetTrialBalanceTool;
use Obelaw\Ium\Accounting\Mcp\Tools\SearchAccountsTool;
use Obelaw\Ium\Mcp\Contracts\McpToolset;

final class AccountingToolset implements McpToolset
{
    public function module(): ?string
    {
        return 'accounting';
    }

    public function label(): string
    {
        return 'Accounting & General Ledger';
    }

    public function tools(): array
    {
        return [
            SearchAccountsTool::class,
            GetTrialBalanceTool::class,
            CreateDraftJournalEntryTool::class,
        ];
    }

    public function resources(): array
    {
        return [];
    }

    public function prompts(): array
    {
        return [];
    }
}
