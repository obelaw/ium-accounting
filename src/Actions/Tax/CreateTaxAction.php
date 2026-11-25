<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\Tax;

use InvalidArgumentException;
use Obelaw\Ium\Accounting\Data\CreateTaxData;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Exceptions\AccountNotFoundException;
use Obelaw\Ium\Accounting\Exceptions\NonPostingAccountException;
use Obelaw\Ium\Accounting\Models\Account;
use Obelaw\Ium\Accounting\Models\Tax;
use Obelaw\Ium\Accounting\Support\AuditRecorder;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;

/**
 * @extends Action<CreateTaxData, Tax>
 */
final class CreateTaxAction extends Action
{
    protected ?string $ability = 'accounting.tax.create';

    public function __construct(private readonly AuditRecorder $audit) {}

    protected function handle(mixed $input, Actor $actor): Tax
    {
        if (Tax::where('code', $input->code)->exists()) {
            throw new InvalidArgumentException("Tax code [{$input->code}] already exists.");
        }

        $taxAccount = Account::find($input->taxAccountId)
            ?? throw AccountNotFoundException::forId($input->taxAccountId);

        if (! $taxAccount->allowsPostings()) {
            throw NonPostingAccountException::forAccount($taxAccount->code, $taxAccount->name, $taxAccount->classification->value);
        }

        if ($input->refundAccountId !== null) {
            $refundAccount = Account::find($input->refundAccountId)
                ?? throw AccountNotFoundException::forId($input->refundAccountId);
            if (! $refundAccount->allowsPostings()) {
                throw NonPostingAccountException::forAccount($refundAccount->code, $refundAccount->name, $refundAccount->classification->value);
            }
        }

        return Tax::create([
            'code' => $input->code,
            'name' => $input->name,
            'scope' => $input->scope,
            'type' => $input->type,
            'rate_bps' => $input->rateBps,
            'fixed_amount' => $input->fixedAmount,
            'is_inclusive' => $input->isInclusive,
            'is_withholding' => $input->isWithholding,
            'tax_account_id' => $input->taxAccountId,
            'refund_account_id' => $input->refundAccountId,
            'is_active' => $input->isActive,
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
