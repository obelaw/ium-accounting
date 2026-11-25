<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\Currency;

use Carbon\Carbon;
use InvalidArgumentException;
use Obelaw\Ium\Accounting\Data\CreateCurrencyData;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Events\CurrencyCreated;
use Obelaw\Ium\Accounting\Models\Currency;
use Obelaw\Ium\Accounting\Models\ExchangeRate;
use Obelaw\Ium\Accounting\Support\AuditRecorder;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;

/**
 * @extends Action<CreateCurrencyData, Currency>
 */
final class CreateCurrencyAction extends Action
{
    protected ?string $ability = 'accounting.currency.create';

    public function __construct(private readonly AuditRecorder $audit) {}

    protected function handle(mixed $input, Actor $actor): Currency
    {
        if (Currency::where('code', $input->code)->exists()) {
            throw new InvalidArgumentException("Currency [{$input->code}] already exists.");
        }

        $rate = $input->isBase ? 1.0 : $input->exchangeRate;

        if ($input->isBase) {
            Currency::where('is_base', true)->update(['is_base' => false]);
        }

        $currency = Currency::create([
            'code' => $input->code,
            'name' => $input->name,
            'symbol' => $input->symbol,
            'precision' => $input->precision,
            'symbol_first' => $input->symbolFirst,
            'decimal_mark' => $input->decimalMark,
            'thousands_separator' => $input->thousandsSeparator,
            'is_base' => $input->isBase,
            'is_active' => $input->isActive,
            'exchange_rate' => $rate,
            'metadata' => $input->metadata,
        ]);

        ExchangeRate::create([
            'currency_id' => $currency->id,
            'currency_code' => $currency->code,
            'rate' => round($rate, 8),
            'inverse_rate' => round(1 / $rate, 8),
            'effective_date' => Carbon::today()->format('Y-m-d'),
            'source' => 'initial_seed',
        ]);

        $this->recordEvent(new CurrencyCreated($currency, $actor->id));

        return $currency;
    }

    protected function audit(Actor $actor, mixed $input, mixed $result): void
    {
        $this->audit->record(
            action: AuditAction::CREATED,
            auditable: $result,
            after: $result->toArray(),
            actorId: $actor->id,
            originContext: self::class,
        );
    }
}
