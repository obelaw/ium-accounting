<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Reports;

use Obelaw\Ium\Accounting\Models\JournalEntry;

final readonly class FxRevaluationResult
{
    public function __construct(
        public string $currency,
        public float $closingRate,
        public string $asOfDate,
        public int $netGainLoss,
        public ?JournalEntry $revaluationEntry,
        public ?JournalEntry $autoReversalEntry,
    ) {}
}
