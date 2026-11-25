<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Actions\Currency;

use Carbon\Carbon;
use Obelaw\Ium\Accounting\Enums\AuditAction;
use Obelaw\Ium\Accounting\Events\BaseCurrencyChanged;
use Obelaw\Ium\Accounting\Exceptions\CurrencyNotFoundException;
use Obelaw\Ium\Accounting\Models\Currency;
use Obelaw\Ium\Accounting\Models\ExchangeRate;
use Obelaw\Ium\Accounting\Support\AuditRecorder;
use Obelaw\Ium\Actions\Action;
use Obelaw\Ium\Contracts\Actor;

/**
 * @extends Action<string, Currency>
 */
final class SetBaseCurrencyAction extends Action
{
    protected ?string $ability = 'accounting.currency.set_base';

    public function __construct(private readonly AuditRecorder $audit) {}

    protected function handle(mixed $input, Actor $actor): Currency
    {
        $code = strtoupper(trim((string) $input));
        $target = Currency::where('code', $code)->lockForUpdate()->first()
            ?? throw CurrencyNotFoundException::forCode($code);

        if ($target->isBase()) {
            return $target;
        }

        $previousBase = Currency::where('is_base', true)->lockForUpdate()->first();
        $previousBase?->update(['is_base' => false]);

        $target->update(['is_base' => true, 'exchange_rate' => 1.0]);

        ExchangeRate::updateOrCreate(
            ['currency_code' => $target->code, 'effective_date' => Carbon::today()->format('Y-m-d')],
            ['currency_id' => $target->id, 'rate' => 1.0, 'inverse_rate' => 1.0, 'source' => 'base_switch'],
        );

        $this->recordEvent(new BaseCurrencyChanged($previousBase, $target, $actor->id));

        return $target->fresh();
    }

    protected function audit(Actor $actor, mixed $input, mixed $result): void
    {
        $this->audit->record(
            action: AuditAction::UPDATED,
            auditable: $result,
            after: ['is_base' => true, 'code' => $result->code],
            actorId: $actor->id,
            originContext: self::class,
        );
    }
}
