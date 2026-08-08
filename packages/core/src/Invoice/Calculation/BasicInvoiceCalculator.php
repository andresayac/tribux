<?php

declare(strict_types=1);

namespace Tribux\Core\Invoice\Calculation;

use Tribux\Core\Invoice\Invoice;
use Tribux\Core\Invoice\InvoiceLine;
use Tribux\Core\Money\Money;

/**
 * Calculates the initial tax-exclusive invoice profile.
 *
 * It intentionally does not model allowances, charges, tax-inclusive prices or
 * withholdings. Those concepts require separate domain inputs and policies.
 */
final class BasicInvoiceCalculator
{
    public function calculate(Invoice $invoice, InvoiceCalculationPolicy $policy): CalculatedInvoice
    {
        $currency = $invoice->lines[0]->unitPrice->currency;
        $zero = $this->zero($currency, $policy->moneyScale);
        $lineExtensionAmount = $zero;
        $taxAmount = $zero;
        $calculatedLines = [];
        /** @var array<string, CalculatedTax> $taxBuckets */
        $taxBuckets = [];

        foreach ($invoice->lines as $line) {
            $calculated = $this->calculateLine($line, $policy);
            $calculatedLines[] = $calculated;
            $lineExtensionAmount = $lineExtensionAmount->plus($calculated->lineExtensionAmount);
            $taxAmount = $taxAmount->plus($calculated->taxAmount);

            foreach ($calculated->taxes as $tax) {
                $key = strlen($tax->type).':'.$tax->type.$tax->rate->percent;
                $taxBuckets[$key] = isset($taxBuckets[$key]) ? $taxBuckets[$key]->plus($tax) : $tax;
            }
        }

        $taxInclusiveAmount = $lineExtensionAmount->plus($taxAmount);

        return new CalculatedInvoice(
            lines: $calculatedLines,
            taxes: array_values($taxBuckets),
            lineExtensionAmount: $lineExtensionAmount,
            taxExclusiveAmount: $lineExtensionAmount,
            taxAmount: $taxAmount,
            taxInclusiveAmount: $taxInclusiveAmount,
            payableAmount: $taxInclusiveAmount,
        );
    }

    private function calculateLine(InvoiceLine $line, InvoiceCalculationPolicy $policy): CalculatedInvoiceLine
    {
        $lineExtensionAmount = $line->unitPrice->multipliedBy(
            $line->quantity,
            $policy->moneyScale,
            $policy->roundingMode,
        );
        $taxAmount = $this->zero($line->unitPrice->currency, $policy->moneyScale);
        $taxes = [];

        foreach ($line->taxes as $tax) {
            $amount = $lineExtensionAmount->percentage(
                $tax->rate,
                $policy->moneyScale,
                $policy->roundingMode,
            );
            $taxes[] = new CalculatedTax(
                type: $tax->type,
                rate: $tax->rate,
                taxableAmount: $lineExtensionAmount,
                taxAmount: $amount,
            );
            $taxAmount = $taxAmount->plus($amount);
        }

        return new CalculatedInvoiceLine(
            lineExtensionAmount: $lineExtensionAmount,
            taxes: $taxes,
            taxAmount: $taxAmount,
            taxInclusiveAmount: $lineExtensionAmount->plus($taxAmount),
        );
    }

    private function zero(string $currency, int $scale): Money
    {
        return new Money($scale === 0 ? '0' : '0.'.str_repeat('0', $scale), $currency);
    }
}
