<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\Account;

use Obelaw\Ium\Accounting\Enums\NormalBalance;
use Obelaw\Ium\Accounting\Exceptions\AccountNotFoundException;
use Obelaw\Ium\Accounting\Models\Account;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;

/**
 * @extends Action<int, int>
 */
final class RecalculateAccountBalanceAction extends Action
{
    protected function handle(mixed $input, Actor $actor): int
    {
        $account = Account::lockForUpdate()->find((int) $input)
            ?? throw AccountNotFoundException::forId((int) $input);

        $debits = (int) $account->ledgerPostings()->sum('debit');
        $credits = (int) $account->ledgerPostings()->sum('credit');

        $balance = $account->normal_balance === NormalBalance::DEBIT
            ? ($debits - $credits)
            : ($credits - $debits);

        $account->update(['current_balance' => $balance]);

        return $balance;
    }
}
