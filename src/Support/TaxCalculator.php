<?php

declare(strict_types=1);

namespace Obelaw\Ium\Accounting\Support;

use Obelaw\Ium\Accounting\Enums\TaxType;
use Obelaw\Ium\Accounting\Models\Tax;
use Obelaw\Ium\Accounting\Reports\TaxBreakdownLine;
use Obelaw\Ium\Accounting\Reports\TaxCalculationResult;

/**
 * Pure integer minor-unit Tax / VAT & Withholding calculator using basis points (10,000 bps = 100%).
 * Zero IEEE-754 floating-point drift.
 */
final class TaxCalculator
{
    public function calculate(int $amountMinor, Tax $tax): TaxCalculationResult
    {
        return $this->calculateGroup($amountMinor, [$tax]);
    }

    /**
     * Calculate one or more taxes applied to a single base amount.
     *
     * @param list<Tax> $taxes
     */
    public function calculateGroup(int $amountMinor, array $taxes): TaxCalculationResult
    {
        $inclusiveBps = 0;
        $inclusiveFixed = 0;

        foreach ($taxes as $tax) {
            if (! $tax->is_inclusive) {
                continue;
            }

            if ($tax->type === TaxType::PERCENTAGE) {
                $inclusiveBps += $tax->is_withholding ? -$tax->rate_bps : $tax->rate_bps;
            } else {
                $inclusiveFixed += $tax->is_withholding ? -$tax->fixed_amount : $tax->fixed_amount;
            }
        }

        // Extract net base from tax-inclusive gross amount
        $netBase = $amountMinor - $inclusiveFixed;
        if ($inclusiveBps !== 0) {
            $netBase = (int) round(($netBase * 10000) / (10000 + $inclusiveBps), 0, PHP_ROUND_HALF_UP);
        }

        $lines = [];
        $totalTax = 0;
        $totalWithholding = 0;

        foreach ($taxes as $tax) {
            $computed = match ($tax->type) {
                TaxType::PERCENTAGE => (int) round(($netBase * $tax->rate_bps) / 10000, 0, PHP_ROUND_HALF_UP),
                TaxType::FIXED => (int) $tax->fixed_amount,
            };

            $signedTax = $tax->is_withholding ? -$computed : $computed;

            if ($tax->is_withholding) {
                $totalWithholding += $computed;
            } else {
                $totalTax += $computed;
            }

            $lines[] = new TaxBreakdownLine(
                taxId: $tax->id,
                taxCode: $tax->code,
                taxName: $tax->name,
                taxAccountId: $tax->tax_account_id,
                baseAmount: $netBase,
                taxAmount: $signedTax,
                isWithholding: (bool) $tax->is_withholding,
                isInclusive: (bool) $tax->is_inclusive,
            );
        }

        // If all taxes were inclusive (no exclusive taxes), reconcile any 1-minor-unit rounding into base
        $hasInclusive = array_reduce($taxes, static fn (bool $carry, Tax $t): bool => $carry || $t->is_inclusive, false);
        $hasExclusive = array_reduce($taxes, static fn (bool $carry, Tax $t): bool => $carry || ! $t->is_inclusive, false);

        if ($hasInclusive && ! $hasExclusive) {
            $netTax = $totalTax - $totalWithholding;
            $netBase = $amountMinor - $netTax;
            $grossTotal = $amountMinor;
        } else {
            $grossTotal = $netBase + $totalTax - $totalWithholding;
        }

        return new TaxCalculationResult(
            netAmount: $netBase,
            totalTax: $totalTax,
            totalWithholding: $totalWithholding,
            grossAmount: $grossTotal,
            lines: $lines,
        );
    }
}
