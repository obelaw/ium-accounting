<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Obelaw\Ium\Accounting\Data\CreateAccountDto;
use Obelaw\Ium\Accounting\Data\UpdateAccountDto;
use Obelaw\Ium\Accounting\Enums\AccountClassification;
use Obelaw\Ium\Accounting\Exceptions\AccountNotFoundException;
use Obelaw\Ium\Accounting\Models\Account;

class AccountService
{
    /**
     * Create a new Account in the Chart of Accounts tree.
     */
    public function create(CreateAccountDto $dto): Account
    {
        return DB::transaction(function () use ($dto) {
            if (Account::where('code', $dto->code)->exists()) {
                throw new InvalidArgumentException(sprintf('Account code "%s" already exists.', $dto->code));
            }

            if ($dto->parentId !== null) {
                $parent = Account::find($dto->parentId);
                if (!$parent) {
                    throw AccountNotFoundException::forId($dto->parentId);
                }

                // If parent was previously marked as leaf, promote it to non-leaf aggregator
                if ($parent->is_leaf) {
                    $parent->update(['is_leaf' => false]);
                }
            }

            return Account::create([
                'code' => $dto->code,
                'name' => $dto->name,
                'type' => $dto->type,
                'classification' => $dto->classification,
                'normal_balance' => $dto->normalBalance,
                'parent_id' => $dto->parentId,
                'currency' => $dto->currency,
                'is_leaf' => $dto->isLeaf,
                'is_reconciliation' => $dto->isReconciliation,
                'is_active' => $dto->isActive,
                'current_balance' => 0.0,
                'metadata' => $dto->metadata,
            ]);
        });
    }

    /**
     * Update an existing Account.
     */
    public function update(int $id, UpdateAccountDto $dto): Account
    {
        return DB::transaction(function () use ($id, $dto) {
            $account = $this->findOrFail($id);

            $data = $dto->toArray();
            if (!empty($data)) {
                $account->update($data);
            }

            return $account->fresh();
        });
    }

    public function find(int $id): ?Account
    {
        return Account::find($id);
    }

    public function findByCode(string $code): ?Account
    {
        return Account::where('code', trim($code))->first();
    }

    public function findOrFail(int $id): Account
    {
        return Account::find($id) ?? throw AccountNotFoundException::forId($id);
    }

    public function findByCodeOrFail(string $code): Account
    {
        return $this->findByCode($code) ?? throw AccountNotFoundException::forCode($code);
    }

    /**
     * Retrieve the hierarchical Chart of Accounts tree (root nodes with children).
     *
     * @return Collection<int, Account>
     */
    public function tree(): Collection
    {
        return Account::with('children.children.children')
            ->whereNull('parent_id')
            ->orderBy('code')
            ->get();
    }

    /**
     * Retrieve all leaf posting accounts.
     *
     * @return Collection<int, Account>
     */
    public function postingAccounts(): Collection
    {
        return Account::postingAccounts()->orderBy('code')->get();
    }

    /**
     * Recalculate balance for an account from general ledger postings.
     */
    public function recalculateBalance(int $accountId): float
    {
        return DB::transaction(function () use ($accountId) {
            $account = $this->findOrFail($accountId);

            $debits = (float) $account->ledgerPostings()->sum('debit');
            $credits = (float) $account->ledgerPostings()->sum('credit');

            $balance = $account->normal_balance->value === 'debit'
                ? ($debits - $credits)
                : ($credits - $debits);

            $account->update(['current_balance' => round($balance, 4)]);

            return $account->current_balance;
        });
    }
}
