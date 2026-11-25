<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\Account;

use InvalidArgumentException;
use Obelaw\Ium\Accounting\Data\CreateAccountData;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Exceptions\AccountNotFoundException;
use Obelaw\Ium\Accounting\Models\Account;
use Obelaw\Ium\Accounting\Support\AuditRecorder;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;

/**
 * @extends Action<CreateAccountData, Account>
 */
final class CreateAccountAction extends Action
{
    protected ?string $ability = 'accounting.account.create';

    public function __construct(private readonly AuditRecorder $audit) {}

    protected function handle(mixed $input, Actor $actor): Account
    {
        if (Account::where('code', $input->code)->exists()) {
            throw new InvalidArgumentException("Account code [{$input->code}] already exists.");
        }

        if ($input->parentId !== null) {
            $parent = Account::lockForUpdate()->find($input->parentId);
            if (! $parent) {
                throw AccountNotFoundException::forId($input->parentId);
            }

            if ($parent->is_leaf) {
                $parent->update(['is_leaf' => false]);
            }
        }

        return Account::create([
            'code' => $input->code,
            'name' => $input->name,
            'type' => $input->type,
            'classification' => $input->classification,
            'normal_balance' => $input->normalBalance,
            'parent_id' => $input->parentId,
            'currency' => $input->currency ? strtoupper(trim($input->currency)) : null,
            'is_leaf' => $input->isLeaf,
            'is_reconcilable' => $input->isReconcilable,
            'is_active' => $input->isActive,
            'current_balance' => 0,
            'metadata' => $input->metadata,
        ]);
    }

    protected function audit(Actor $actor, mixed $input, mixed $result): void
    {
        $this->audit->record(
            action: AuditAction::CREATED,
            auditable: $result,
            after: $result->toArray(),
            actorId: $actor->id,
            originContext: self::class,
        );
    }
}
