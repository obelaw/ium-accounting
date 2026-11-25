<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Gateways;

use Illuminate\Database\Eloquent\Collection;
use Obelaw\Ium\Accounting\Actions\CostCenter\CreateCostCenterAction;
use Obelaw\Ium\Accounting\Data\CreateCostCenterData;
use Obelaw\Ium\Accounting\Enums\DimensionType;
use Obelaw\Ium\Accounting\Models\CostCenter;

final class CostCenterGateway
{
    public function __construct(private readonly CreateCostCenterAction $createAction) {}

    public function create(CreateCostCenterData $data): CostCenter
    {
        return $this->createAction->execute($data);
    }

    public function find(int $id): ?CostCenter
    {
        return CostCenter::find($id);
    }

    public function findByCode(string $code): ?CostCenter
    {
        return CostCenter::where('code', strtoupper(trim($code)))->first();
    }

    /**
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
