<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\Currency;

use Carbon\Carbon;
use Obelaw\Ium\Accounting\Data\SetExchangeRateData;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Events\ExchangeRateUpdated;
use Obelaw\Ium\Accounting\Exceptions\BaseCurrencyRateImmutableException;
use Obelaw\Ium\Accounting\Exceptions\CurrencyNotFoundException;
use Obelaw\Ium\Accounting\Models\Currency;
use Obelaw\Ium\Accounting\Models\ExchangeRate;
use Obelaw\Ium\Accounting\Support\AuditRecorder;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;

/**
 * @extends Action<SetExchangeRateData, ExchangeRate>
 */
final class SetExchangeRateAction extends Action
{
    protected ?string $ability = 'accounting.currency.set_rate';

    public function __construct(private readonly AuditRecorder $audit) {}

    protected function handle(mixed $input, Actor $actor): ExchangeRate
    {
        $currency = Currency::where('code', $input->currencyCode)->lockForUpdate()->first()
            ?? throw CurrencyNotFoundException::forCode($input->currencyCode);

        $rate = $input->rate;
        if ($currency->isBase()) {
            if (abs($rate - 1.0) > 0.000001) {
                throw BaseCurrencyRateImmutableException::cannotAlterBaseRate($currency->code, $rate);
            }
            $rate = 1.0;
        }

        $effectiveDate = $input->effectiveDate
            ? Carbon::parse($input->effectiveDate)->format('Y-m-d')
            : Carbon::today()->format('Y-m-d');

        $directRate = $input->isDirect ? round($rate, 8) : round(1 / $rate, 8);
        $inverseRate = $input->isDirect ? round(1 / $rate, 8) : round($rate, 8);

        $exchangeRate = ExchangeRate::updateOrCreate(
            ['currency_code' => $currency->code, 'effective_date' => $effectiveDate],
            [
                'currency_id' => $currency->id,
                'rate' => $directRate,
                'inverse_rate' => $inverseRate,
                'source' => $input->source,
            ],
        );

        $latestDate = ExchangeRate::where('currency_code', $currency->code)->max('effective_date');
        if ($latestDate === null || $effectiveDate >= (string) $latestDate) {
            $currency->update(['exchange_rate' => $directRate]);
        }

        $this->recordEvent(new ExchangeRateUpdated($exchangeRate, $actor->id));

        return $exchangeRate;
    }

    protected function audit(Actor $actor, mixed $input, mixed $result): void
    {
        $this->audit->record(
            action: AuditAction::UPDATED,
            auditable: $result,
            after: $result->toArray(),
            actorId: $actor->id,
            originContext: self::class,
        );
    }
}
