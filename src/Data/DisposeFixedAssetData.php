<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use Carbon\Carbon;
use InvalidArgumentException;

final readonly class DisposeFixedAssetData
{
    public string $disposalDate;

    public function __construct(
        public int $assetId,
        string $disposalDate,
        public int $proceedsMinor = 0,
        public ?int $proceedsAccountId = null,
        public ?int $gainAccountId = null,
        public ?int $lossAccountId = null,
        public bool $postDisposalEntry = true,
        public ?string $reason = null,
    ) {
        if ($proceedsMinor < 0) {
            throw new InvalidArgumentException('Disposal proceeds cannot be negative.');
        }

        if ($postDisposalEntry && $proceedsMinor > 0 && $proceedsAccountId === null) {
            throw new InvalidArgumentException('proceedsAccountId (e.g. Cash/Bank/AR) is required when proceedsMinor > 0 and postDisposalEntry is enabled.');
        }

        $this->disposalDate = Carbon::parse($disposalDate)->format('Y-m-d');
    }
}
