<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use InvalidArgumentException;

final readonly class UpdateCurrencyDto
{
    public function __construct(
        public ?string $name = null,
        public ?string $symbol = null,
        public ?int $precision = null,
        public ?bool $symbolFirst = null,
        public ?string $decimalMark = null,
        public ?string $thousandsSeparator = null,
        public ?bool $isActive = null,
        public ?string $actorId = null,
        public ?array $metadata = null,
    ) {
        if ($precision !== null && ($precision < 0 || $precision > 6)) {
            throw new InvalidArgumentException('Currency precision must be between 0 and 6 decimal places.');
        }
    }

    public static function from(array $data): self
    {
        return new self(
            name: isset($data['name']) ? trim((string) $data['name']) : null,
            symbol: isset($data['symbol']) ? trim((string) $data['symbol']) : null,
            precision: isset($data['precision']) ? (int) $data['precision'] : null,
            symbolFirst: isset($data['symbol_first']) ? (bool) $data['symbol_first'] : null,
            decimalMark: isset($data['decimal_mark']) ? (string) $data['decimal_mark'] : null,
            thousandsSeparator: isset($data['thousands_separator']) ? (string) $data['thousands_separator'] : null,
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : null,
            actorId: isset($data['actor_id']) ? (string) $data['actor_id'] : null,
            metadata: isset($data['metadata']) ? (array) $data['metadata'] : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'symbol' => $this->symbol,
            'precision' => $this->precision,
            'symbol_first' => $this->symbolFirst,
            'decimal_mark' => $this->decimalMark,
            'thousands_separator' => $this->thousandsSeparator,
            'is_active' => $this->isActive,
            'actor_id' => $this->actorId,
            'metadata' => $this->metadata,
        ], static fn($value) => $value !== null);
    }
}
