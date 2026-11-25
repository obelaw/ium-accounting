<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Gateways;

use Illuminate\Database\Eloquent\Collection;
use Obelaw\Ium\Accounting\Actions\Currency\CreateCurrencyAction;
use Obelaw\Ium\Accounting\Actions\Currency\SetBaseCurrencyAction;
use Obelaw\Ium\Accounting\Actions\Currency\SetExchangeRateAction;
use Obelaw\Ium\Accounting\Contracts\ExchangeRateProviderInterface;
use Obelaw\Ium\Accounting\Data\CreateCurrencyData;
use Obelaw\Ium\Accounting\Data\SetExchangeRateData;
use Obelaw\Ium\Accounting\Exceptions\CurrencyNotFoundException;
use Obelaw\Ium\Accounting\Models\Currency;
use Obelaw\Ium\Accounting\Models\ExchangeRate;
use Obelaw\Ium\Accounting\Support\ExchangeRateResolver;

final class CurrencyGateway
{
    public function __construct(
        private readonly CreateCurrencyAction $createAction,
        private readonly SetBaseCurrencyAction $setBaseAction,
        private readonly SetExchangeRateAction $setRateAction,
        private readonly ExchangeRateResolver $resolver,
    ) {}

    public function create(CreateCurrencyData $data): Currency
    {
        return $this->createAction->execute($data);
    }

    public function setBase(string $code): Currency
    {
        return $this->setBaseAction->execute($code);
    }

    public function setRate(SetExchangeRateData $data): ExchangeRate
    {
        return $this->setRateAction->execute($data);
    }

    public function base(): Currency
    {
        return $this->resolver->baseCurrency();
    }

    public function getRate(string $from, string $to, ?string $date = null): float
    {
        return $this->resolver->getRate($from, $to, $date);
    }

    public function convertMinor(int $minorAmount, string $from, string $to, ?string $date = null): int
    {
        return $this->resolver->convertMinor($minorAmount, $from, $to, $date);
    }

    public function setRateProvider(?ExchangeRateProviderInterface $provider): self
    {
        $this->resolver->setProvider($provider);

        return $this;
    }

    public function findByCode(string $code): ?Currency
    {
        return Currency::where('code', strtoupper(trim($code)))->first();
    }

    public function findByCodeOrFail(string $code): Currency
    {
        $clean = strtoupper(trim($code));

        return $this->findByCode($clean) ?? throw CurrencyNotFoundException::forCode($clean);
    }

    /**
     * @return Collection<int, Currency>
     */
    public function all(bool $onlyActive = true): Collection
    {
        $query = Currency::query();
        if ($onlyActive) {
            $query->active();
        }

        return $query->orderBy('code')->get();
    }

    public function seedStandard(string $baseCurrency = 'USD'): void
    {
        $standards = [
            ['USD', 'US Dollar', '$', 2, true],
            ['EUR', 'Euro', '€', 2, false],
            ['GBP', 'British Pound', '£', 2, true],
            ['SAR', 'Saudi Riyal', 'SAR', 2, false],
            ['AED', 'UAE Dirham', 'AED', 2, false],
            ['EGP', 'Egyptian Pound', 'EGP', 2, false],
            ['KWD', 'Kuwaiti Dinar', 'KWD', 3, false],
            ['JPY', 'Japanese Yen', '¥', 0, true],
        ];

        $base = strtoupper(trim($baseCurrency));

        foreach ($standards as [$code, $name, $symbol, $precision, $symbolFirst]) {
            if (! Currency::where('code', $code)->exists()) {
                $this->create(new CreateCurrencyData(
                    code: $code,
                    name: $name,
                    symbol: $symbol,
                    precision: $precision,
                    symbolFirst: $symbolFirst,
                    isBase: $code === $base,
                ));
            }
        }
    }
}
