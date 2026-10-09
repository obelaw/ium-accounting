<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Obelaw\Ium\Accounting\Data\CreateCostCenterDto;
use Obelaw\Ium\Accounting\Enums\DimensionType;
use Obelaw\Ium\Accounting\Models\CostCenter;

class CostCenterService
{
    /**
     * Create a new analytical cost center / project / department.
     */
    public function create(CreateCostCenterDto $dto): CostCenter
    {
        return DB::transaction(function () use ($dto) {
            if (CostCenter::where('code', $dto->code)->exists()) {
                throw new InvalidArgumentException(sprintf('Cost center code "%s" already exists.', $dto->code));
            }

            return CostCenter::create([
                'code' => $dto->code,
                'name' => $dto->name,
                'dimension_type' => $dto->dimensionType,
                'parent_id' => $dto->parentId,
                'is_active' => $dto->isActive,
                'metadata' => $dto->metadata,
            ]);
        });
    }

    public function find(int $id): ?CostCenter
    {
        return CostCenter::find($id);
    }

    public function findByCode(string $code): ?CostCenter
    {
        return CostCenter::where('code', trim($code))->first();
    }

    /**
     * Retrieve cost centers, optionally filtered by dimension type.
     *
     * @return Collection<int, CostCenter>
     */
    public function list(?DimensionType $type = null): Collection
    {
        $query = CostCenter::query()->active()->orderBy('code');

        if ($type !== null) {
            $query->ofType($type);
        }

        return $query->get();
    }
}
