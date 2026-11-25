<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Exceptions;

final class InvalidStateTransitionException extends AccountingException
{
    public static function transition(string $identifier, string $from, string $to): self
    {
        return new self(sprintf(
            'Invalid state transition for "%s": cannot transition from "%s" to "%s".',
            $identifier,
            $from,
            $to
        ));
    }
}
