<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Data;

use InvalidArgumentException;
use Obelaw\Ium\Accounting\Enums\DimensionType;

final readonly class CreateCostCenterData
{
    public string $code;

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        string $code,
        public string $name,
        public DimensionType $dimensionType = DimensionType::COST_CENTER,
        public ?int $parentId = null,
        public bool $isActive = true,
        public array $metadata = [],
    ) {
        $clean = strtoupper(trim($code));
        if ($clean === '') {
            throw new InvalidArgumentException('Cost center code cannot be empty.');
        }

        $this->code = $clean;
    }
}
