<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\CostCenter;

use InvalidArgumentException;
use Obelaw\Ium\Accounting\Data\CreateCostCenterData;
use Obelaw\Ium\Accounting\Models\CostCenter;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;

/**
 * @extends Action<CreateCostCenterData, CostCenter>
 */
final class CreateCostCenterAction extends Action
{
    protected ?string $ability = 'accounting.cost_center.create';

    protected function handle(mixed $input, Actor $actor): CostCenter
    {
        if (CostCenter::where('code', $input->code)->exists()) {
            throw new InvalidArgumentException("Cost center code [{$input->code}] already exists.");
        }

        return CostCenter::create([
            'code' => $input->code,
            'name' => $input->name,
            'dimension_type' => $input->dimensionType,
            'parent_id' => $input->parentId,
            'is_active' => $input->isActive,
            'metadata' => $input->metadata,
        ]);
    }
}
