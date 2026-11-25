<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Exceptions;

final class MakerCheckerViolationException extends AccountingException
{
    public static function sameActor(string $actorId): self
    {
        return new self(sprintf(
            'Maker-Checker segregation violation: actor "%s" cannot approve a voucher they created.',
            $actorId
        ));
    }
}
