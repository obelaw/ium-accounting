<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use InvalidArgumentException;
use Obelaw\Ium\Accounting\Enums\FiscalPeriodStatus;

final readonly class CreateFiscalPeriodDto
{
    public function __construct(
        public int $fiscalYearId,
        public int $periodNumber,
        public string $name,
        public string $startDate,
        public string $endDate,
        public FiscalPeriodStatus $status = FiscalPeriodStatus::OPEN,
        public ?string $actorId = null,
    ) {}

    public static function from(array $data): self
    {
        $fiscalYearId = (int) ($data['fiscal_year_id'] ?? throw new InvalidArgumentException('fiscal_year_id is required.'));
        $periodNumber = (int) ($data['period_number'] ?? throw new InvalidArgumentException('period_number is required.'));
        $name = trim((string) ($data['name'] ?? throw new InvalidArgumentException('Period name is required.')));
        $startDate = (string) ($data['start_date'] ?? throw new InvalidArgumentException('start_date is required.'));
        $endDate = (string) ($data['end_date'] ?? throw new InvalidArgumentException('end_date is required.'));

        $rawStatus = $data['status'] ?? FiscalPeriodStatus::OPEN;
        $status = $rawStatus instanceof FiscalPeriodStatus ? $rawStatus : FiscalPeriodStatus::from((string) $rawStatus);

        return new self(
            fiscalYearId: $fiscalYearId,
            periodNumber: $periodNumber,
            name: $name,
            startDate: $startDate,
            endDate: $endDate,
            status: $status,
            actorId: isset($data['actor_id']) ? (string) $data['actor_id'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'fiscal_year_id' => $this->fiscalYearId,
            'period_number' => $this->periodNumber,
            'name' => $this->name,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'status' => $this->status->value,
            'actor_id' => $this->actorId,
        ];
    }
}
