<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use Carbon\Carbon;
use InvalidArgumentException;

final readonly class ReconcileLinesData
{
    public string $reconciledDate;

    /**
     * @param list<int> $debitLineIds Posted open debit line IDs on a reconcilable account.
     * @param list<int> $creditLineIds Posted open credit line IDs on the same reconcilable account.
     * @param int|null $amount Optional explicit cap in transaction currency minor units (for partial reconciliation).
     */
    public function __construct(
        public array $debitLineIds,
        public array $creditLineIds,
        ?string $reconciledDate = null,
        public ?int $amount = null,
        public ?string $notes = null,
        public bool $postFxDifference = true,
    ) {
        if ($debitLineIds === [] || $creditLineIds === []) {
            throw new InvalidArgumentException('Reconciliation requires at least one debit line and one credit line.');
        }

        if ($amount !== null && $amount <= 0) {
            throw new InvalidArgumentException('Explicit reconciliation amount must be positive.');
        }

        $this->reconciledDate = $reconciledDate !== null
            ? Carbon::parse($reconciledDate)->format('Y-m-d')
            : date('Y-m-d');
    }
}
