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
use Obelaw\Ium\Accounting\Models\Account;
use Obelaw\Ium\Mcp\Base\IumTool;

#[Name('accounting.search-accounts')]
#[Title('Search Chart of Accounts')]
#[Description('Search Chart of Accounts by code, name, or type. Always call this before creating a draft journal entry to resolve real posting account IDs.')]
#[IsReadOnly]
#[IsIdempotent]
final class SearchAccountsTool extends IumTool
{
    public function module(): ?string
    {
        return 'accounting';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Optional search query matched against account code or name.'),
            'type' => $schema->string()->description('Optional account type filter (asset, liability, equity, revenue, expense).'),
            'posting_only' => $schema->boolean()->description('If true, returns only leaf posting accounts eligible for journal lines (default true).')->default(true),
            'limit' => $schema->integer()->description('Maximum accounts to return (1-100, default 25).')->default(25),
        ];
    }

    protected function execute(Request $request): array
    {
        $queryText = trim((string) ($request->get('query') ?? ''));
        $type = trim((string) ($request->get('type') ?? ''));
        $postingOnly = (bool) ($request->get('posting_only') ?? true);
        $limit = max(1, min(100, (int) ($request->get('limit') ?? 25)));

        $builder = Account::query()->active()->orderBy('code');

        if ($postingOnly) {
            $builder->postingAccounts();
        }

        if ($queryText !== '') {
            $builder->where(static function ($q) use ($queryText): void {
                $q->where('code', 'like', "%{$queryText}%")
                    ->orWhere('name', 'like', "%{$queryText}%");
            });
        }

        if ($type !== '') {
            $builder->where('type', strtolower($type));
        }

        $accounts = $builder->limit($limit)->get()->map(static fn (Account $account): array => [
            'id' => $account->id,
            'code' => $account->code,
            'name' => $account->name,
            'type' => $account->type instanceof \BackedEnum ? $account->type->value : (string) $account->type,
            'normal_balance' => $account->normal_balance instanceof \BackedEnum ? $account->normal_balance->value : (string) $account->normal_balance,
            'currency' => $account->currency,
            'allows_postings' => $account->allowsPostings(),
            'current_balance_minor' => (int) $account->current_balance,
        ])->all();

        return [
            'count' => count($accounts),
            'accounts' => $accounts,
        ];
    }
}
