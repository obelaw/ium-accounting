<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object validating Chart of Accounts numerical or segmented code.
 */
final readonly class AccountCode
{
    public function __construct(
        public string $value
    ) {
        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Account code cannot be empty.');
        }

        if (!preg_match('/^[0-9A-Z._-]+$/i', $trimmed)) {
            throw new InvalidArgumentException(sprintf('Account code "%s" contains invalid characters.', $trimmed));
        }
    }

    public static function from(string $code): self
    {
        return new self($code);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
