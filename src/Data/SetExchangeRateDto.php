<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use InvalidArgumentException;

final readonly class SetExchangeRateDto
{
    public function __construct(
        public string $currencyCode,
        public float $rate,
        public ?string $effectiveDate = null,
        public string $source = 'manual',
        public bool $isDirect = true,
        public ?string $actorId = null,
        public array $metadata = [],
    ) {
        $cleanCode = strtoupper(trim($currencyCode));
        if (strlen($cleanCode) !== 3) {
            throw new InvalidArgumentException(
                sprintf('Currency code must be a 3-character ISO 4217 code (received "%s").', $currencyCode)
            );
        }

        if ($rate <= 0) {
            throw new InvalidArgumentException(
                sprintf('Exchange rate must be strictly positive (received %F).', $rate)
            );
        }
    }

    public static function from(array $data): self
    {
        $code = strtoupper(trim((string) ($data['currency_code'] ?? $data['code'] ?? throw new InvalidArgumentException('Currency code is required.'))));
        $rate = (float) ($data['rate'] ?? throw new InvalidArgumentException('Exchange rate is required.'));

        return new self(
            currencyCode: $code,
            rate: $rate,
            effectiveDate: isset($data['effective_date']) ? (string) $data['effective_date'] : null,
            source: (string) ($data['source'] ?? 'manual'),
            isDirect: (bool) ($data['is_direct'] ?? true),
            actorId: isset($data['actor_id']) ? (string) $data['actor_id'] : null,
            metadata: (array) ($data['metadata'] ?? []),
        );
    }

    public function toArray(): array
    {
        return [
            'currency_code' => $this->currencyCode,
            'rate' => $this->rate,
            'effective_date' => $this->effectiveDate,
            'source' => $this->source,
            'is_direct' => $this->isDirect,
            'actor_id' => $this->actorId,
            'metadata' => $this->metadata,
        ];
    }
}
