<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Obelaw\Ium\Mcp\Base\IumTool;

#[Name('accounting.get-trial-balance')]
#[Title('Get Trial Balance Report')]
#[Description('Generates the Trial Balance report via ium()->accounting()->reports()->trialBalance() for an optional date range or fiscal period.')]
#[IsReadOnly]
#[IsIdempotent]
final class GetTrialBalanceTool extends IumTool
{
    public function module(): ?string
    {
        return 'accounting';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'from_date' => $schema->string()->description('Optional start date (YYYY-MM-DD).'),
            'to_date' => $schema->string()->description('Optional end date (YYYY-MM-DD).'),
            'fiscal_period_id' => $schema->integer()->description('Optional fiscal period ID.'),
            'include_zero_rows' => $schema->boolean()->description('Include accounts with zero activity (default false).')->default(false),
        ];
    }

    protected function execute(Request $request): array
    {
        $report = ium()->accounting()->reports()->trialBalance(
            fromDate: $request->get('from_date') ? (string) $request->get('from_date') : null,
            toDate: $request->get('to_date') ? (string) $request->get('to_date') : null,
            fiscalPeriodId: $request->get('fiscal_period_id') ? (int) $request->get('fiscal_period_id') : null,
            includeZeroRows: (bool) ($request->get('include_zero_rows') ?? false),
        );

        return method_exists($report, 'toArray')
            ? $report->toArray()
            : (array) $report;
    }
}
