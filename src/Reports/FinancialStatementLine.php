<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Reports;

final readonly class FinancialStatementLine
{
    public function __construct(
        public int $accountId,
        public string $code,
        public string $name,
        public int $balance,
        public bool $isContra = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'account_id' => $this->accountId,
            'code' => $this->code,
            'name' => $this->name,
            'balance' => $this->balance,
            'is_contra' => $this->isContra,
        ];
    }
}
