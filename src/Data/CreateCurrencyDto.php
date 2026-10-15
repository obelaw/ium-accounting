<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use InvalidArgumentException;

final readonly class CreateCurrencyDto
{
    public function __construct(
        public string $code,
        public string $name,
        public string $symbol,
        public int $precision = 2,
        public bool $symbolFirst = true,
        public string $decimalMark = '.',
        public string $thousandsSeparator = ',',
        public bool $isBase = false,
        public bool $isActive = true,
        public float $exchangeRate = 1.0,
        public ?string $actorId = null,
        public array $metadata = [],
    ) {
        $cleanCode = strtoupper(trim($code));
        if (strlen($cleanCode) !== 3) {
            throw new InvalidArgumentException(
                sprintf('Currency code must be a 3-character ISO 4217 code (received "%s").', $code)
            );
        }

        if (empty(trim($name))) {
            throw new InvalidArgumentException('Currency name cannot be empty.');
        }

        if (empty(trim($symbol))) {
            throw new InvalidArgumentException('Currency symbol cannot be empty.');
        }

        if ($precision < 0 || $precision > 6) {
            throw new InvalidArgumentException('Currency precision must be between 0 and 6 decimal places.');
        }
    }

    public static function from(array $data): self
    {
        $code = strtoupper(trim((string) ($data['code'] ?? throw new InvalidArgumentException('Currency code is required.'))));
        $name = trim((string) ($data['name'] ?? throw new InvalidArgumentException('Currency name is required.')));
        $symbol = trim((string) ($data['symbol'] ?? throw new InvalidArgumentException('Currency symbol is required.')));

        return new self(
            code: $code,
            name: $name,
            symbol: $symbol,
            precision: isset($data['precision']) ? (int) $data['precision'] : 2,
            symbolFirst: (bool) ($data['symbol_first'] ?? true),
            decimalMark: (string) ($data['decimal_mark'] ?? '.'),
            thousandsSeparator: (string) ($data['thousands_separator'] ?? ','),
            isBase: (bool) ($data['is_base'] ?? false),
            isActive: (bool) ($data['is_active'] ?? true),
            exchangeRate: isset($data['exchange_rate']) ? (float) $data['exchange_rate'] : 1.0,
            actorId: isset($data['actor_id']) ? (string) $data['actor_id'] : null,
            metadata: (array) ($data['metadata'] ?? []),
        );
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'symbol' => $this->symbol,
            'precision' => $this->precision,
            'symbol_first' => $this->symbolFirst,
            'decimal_mark' => $this->decimalMark,
            'thousands_separator' => $this->thousandsSeparator,
            'is_base' => $this->isBase,
            'is_active' => $this->isActive,
            'exchange_rate' => $this->exchangeRate,
            'actor_id' => $this->actorId,
            'metadata' => $this->metadata,
        ];
    }
}
