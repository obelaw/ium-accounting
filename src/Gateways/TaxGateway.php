<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Gateways;

use Illuminate\Database\Eloquent\Collection;
use Obelaw\Ium\Accounting\Actions\Tax\CreateTaxAction;
use Obelaw\Ium\Accounting\Data\CreateTaxData;
use Obelaw\Ium\Accounting\Enums\TaxScope;
use Obelaw\Ium\Accounting\Models\Tax;
use Obelaw\Ium\Accounting\Reports\TaxCalculationResult;
use Obelaw\Ium\Accounting\Support\TaxCalculator;

final class TaxGateway
{
    public function __construct(
        private readonly CreateTaxAction $createAction,
        private readonly TaxCalculator $calculator,
    ) {}

    public function create(CreateTaxData $data): Tax
    {
        return $this->createAction->execute($data);
    }

    public function findByCode(string $code): ?Tax
    {
        return Tax::where('code', strtoupper(trim($code)))->first();
    }

    /**
     * @return Collection<int, Tax>
     */
    public function all(?TaxScope $scope = null, bool $onlyActive = true): Collection
    {
        $query = Tax::query();
        if ($onlyActive) {
            $query->active();
        }
        if ($scope !== null) {
            $query->forScope($scope);
        }

        return $query->orderBy('code')->get();
    }

    public function calculate(int $amountMinor, Tax $tax): TaxCalculationResult
    {
        return $this->calculator->calculate($amountMinor, $tax);
    }

    /**
     * @param list<Tax> $taxes
     */
    public function calculateGroup(int $amountMinor, array $taxes): TaxCalculationResult
    {
        return $this->calculator->calculateGroup($amountMinor, $taxes);
    }
}
