<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use Carbon\Carbon;
use InvalidArgumentException;

final readonly class FxRevaluationData
{
    public string $asOfDate;

    public string $currency;

    public function __construct(
        string $currency,
        public float $closingRate,
        string $asOfDate,
        public ?int $journalId = null,
        public ?string $unrealizedGainAccountCode = null,
        public ?string $unrealizedLossAccountCode = null,
        public ?string $autoReverseDate = null,
        public ?string $narration = null,
    ) {
        if ($closingRate <= 0) {
            throw new InvalidArgumentException('Closing FX rate must be strictly positive.');
        }

        $this->currency = strtoupper(trim($currency));
        $this->asOfDate = Carbon::parse($asOfDate)->format('Y-m-d');
    }
}
