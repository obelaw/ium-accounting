<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\Account;

use Obelaw\Ium\Accounting\Data\UpdateAccountData;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Exceptions\AccountNotFoundException;
use Obelaw\Ium\Accounting\Models\Account;
use Obelaw\Ium\Accounting\Support\AuditRecorder;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;

/**
 * @extends Action<UpdateAccountData, Account>
 */
final class UpdateAccountAction extends Action
{
    protected ?string $ability = 'accounting.account.update';

    public function __construct(private readonly AuditRecorder $audit) {}

    protected function handle(mixed $input, Actor $actor): Account
    {
        $account = Account::lockForUpdate()->find($input->accountId)
            ?? throw AccountNotFoundException::forId($input->accountId);

        $before = $account->toArray();
        $updates = $input->toUpdateArray();

        if ($updates !== []) {
            $account->update($updates);
        }

        $fresh = $account->fresh();

        $this->audit->record(
            action: AuditAction::UPDATED,
            auditable: $fresh,
            before: $before,
            after: $fresh->toArray(),
            actorId: $actor->id,
            originContext: self::class,
        );

        return $fresh;
    }
}
