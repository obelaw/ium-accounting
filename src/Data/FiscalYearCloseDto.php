<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use InvalidArgumentException;

final readonly class FiscalYearCloseDto
{
    public function __construct(
        public int $fiscalYearId,
        public string $actorId,
        public ?string $retainedEarningsAccountCode = null,
        public ?string $incomeSummaryAccountCode = null,
        public ?string $closingDate = null,
        public ?string $notes = null,
    ) {
        if (trim($actorId) === '') {
            throw new InvalidArgumentException('actor_id is required.');
        }
    }

    public static function from(array $data): self
    {
        return new self(
            fiscalYearId: (int) ($data['fiscal_year_id'] ?? throw new InvalidArgumentException('fiscal_year_id is required.')),
            actorId: trim((string) ($data['actor_id'] ?? throw new InvalidArgumentException('actor_id is required.'))),
            retainedEarningsAccountCode: isset($data['retained_earnings_account_code']) ? (string) $data['retained_earnings_account_code'] : null,
            incomeSummaryAccountCode: isset($data['income_summary_account_code']) ? (string) $data['income_summary_account_code'] : null,
            closingDate: isset($data['closing_date']) ? (string) $data['closing_date'] : null,
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
        );
    }
}
