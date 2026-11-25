<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Exceptions;

final class ImmutableJournalEntryException extends AccountingException
{
    public static function cannotMutate(string $identifier, string $operation): self
    {
        return new self(sprintf(
            'Immutable ledger violation: cannot %s finalized record "%s". Issue a Storno reversal instead.',
            $operation,
            $identifier
        ));
    }
}
