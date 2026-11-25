<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Gateways;

use Illuminate\Database\Eloquent\Collection;
use Obelaw\Ium\Accounting\Actions\Account\CreateAccountAction;
use Obelaw\Ium\Accounting\Actions\Account\RecalculateAccountBalanceAction;
use Obelaw\Ium\Accounting\Actions\Account\UpdateAccountAction;
use Obelaw\Ium\Accounting\Data\CreateAccountData;
use Obelaw\Ium\Accounting\Data\UpdateAccountData;
use Obelaw\Ium\Accounting\Exceptions\AccountNotFoundException;
use Obelaw\Ium\Accounting\Models\Account;

final class AccountGateway
{
    public function __construct(
        private readonly CreateAccountAction $createAction,
        private readonly UpdateAccountAction $updateAction,
        private readonly RecalculateAccountBalanceAction $recalculateAction,
    ) {}

    public function create(CreateAccountData $data): Account
    {
        return $this->createAction->execute($data);
    }

    public function update(UpdateAccountData $data): Account
    {
        return $this->updateAction->execute($data);
    }

    public function recalculateBalance(int $accountId): int
    {
        return $this->recalculateAction->execute($accountId);
    }

    public function find(int $id): ?Account
    {
        return Account::find($id);
    }

    public function findOrFail(int $id): Account
    {
        return Account::find($id) ?? throw AccountNotFoundException::forId($id);
    }

    public function findByCode(string $code): ?Account
    {
        return Account::where('code', trim($code))->first();
    }

    public function findByCodeOrFail(string $code): Account
    {
        return $this->findByCode($code) ?? throw AccountNotFoundException::forCode($code);
    }

    /**
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
     * @return Collection<int, Account>
     */
    public function postingAccounts(): Collection
    {
        return Account::postingAccounts()->orderBy('code')->get();
    }

    /**
     * @return Collection<int, Account>
     */
    public function reconcilableAccounts(): Collection
    {
        return Account::postingAccounts()->reconcilable()->orderBy('code')->get();
    }
}
