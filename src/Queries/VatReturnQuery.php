<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Queries;

use Carbon\Carbon;
use Obelaw\Ium\Accounting\Models\GeneralLedgerPosting;
use Obelaw\Ium\Accounting\Models\Tax;
use Obelaw\Ium\Accounting\Reports\VatReturnLine;
use Obelaw\Ium\Accounting\Reports\VatReturnReport;
use Obelaw\Ium\Accounting\Support\ExchangeRateResolver;

/**
 * VAT / Tax Return Summary Report for statutory filing periods.
 * Aggregates Output VAT (Credit on Tax account) and Input VAT (Debit on Tax account)
 * directly from immutable GL postings tagged with `tax_id`.
 */
final class VatReturnQuery
{
    public function __construct(private readonly ExchangeRateResolver $fxResolver) {}

    public function get(string $fromDate, string $toDate): VatReturnReport
    {
        $from = Carbon::parse($fromDate)->format('Y-m-d');
        $to = Carbon::parse($toDate)->format('Y-m-d');

        $taxes = Tax::orderBy('code')->get()->keyBy('id');

        $aggregates = GeneralLedgerPosting::query()
            ->selectRaw('tax_id, COALESCE(SUM(credit), 0) as output_tax, COALESCE(SUM(debit), 0) as input_tax')
            ->whereNotNull('tax_id')
            ->whereDate('posting_date', '>=', $from)
            ->whereDate('posting_date', '<=', $to)
            ->groupBy('tax_id')
            ->get();

        $lines = [];
        $totalOutput = 0;
        $totalInput = 0;

        foreach ($aggregates as $agg) {
            /** @var Tax|null $tax */
            $tax = $taxes->get((int) $agg->tax_id);
            if ($tax === null) {
                continue;
            }

            $output = (int) $agg->output_tax;
            $input = (int) $agg->input_tax;
            $net = $output - $input;

            $totalOutput += $output;
            $totalInput += $input;

            $lines[] = new VatReturnLine(
                taxId: $tax->id,
                taxCode: $tax->code,
                taxName: $tax->name,
                scope: $tax->scope->value,
                rateBps: (int) $tax->rate_bps,
                outputTax: $output,
                inputTax: $input,
                netTax: $net,
            );
        }

        return new VatReturnReport(
            currency: $this->fxResolver->baseCurrencyCode(),
            fromDate: $from,
            toDate: $to,
            lines: $lines,
            totalOutputVat: $totalOutput,
            totalInputVat: $totalInput,
            netPayable: $totalOutput - $totalInput,
        );
    }
}
