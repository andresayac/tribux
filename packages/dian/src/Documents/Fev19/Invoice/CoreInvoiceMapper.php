<?php

declare(strict_types=1);

namespace Tribux\Dian\Documents\Fev19\Invoice;

use InvalidArgumentException;
use LogicException;
use Tribux\Core\Decimal\DecimalArithmetic;
use Tribux\Core\Invoice\Calculation\BasicInvoiceCalculator;
use Tribux\Core\Invoice\Calculation\CalculatedTax;
use Tribux\Core\Invoice\Invoice;
use Tribux\Core\Money\Money;
use Tribux\Dian\Cufe\CufeCalculator;
use Tribux\Dian\Cufe\CufeInput;
use Tribux\Dian\Software\SoftwareSecurityCodeCalculator;

/** Maps the initial tax-exclusive core invoice profile to FEV 1.9. */
final readonly class CoreInvoiceMapper
{
    public function __construct(
        private BasicInvoiceCalculator $invoiceCalculator = new BasicInvoiceCalculator(),
        private CufeCalculator $cufeCalculator = new CufeCalculator(),
        private SoftwareSecurityCodeCalculator $softwareCalculator = new SoftwareSecurityCodeCalculator(),
    ) {
    }

    public function map(Invoice $invoice, InvoiceGenerationContext $context): InvoiceDocument
    {
        if ($invoice->number === null) {
            throw new InvalidArgumentException('A reserved invoice number is required before FEV 1.9 generation.');
        }

        if ($invoice->issuerId !== $context->issuerReference) {
            throw new InvalidArgumentException('Invoice issuer does not match the FEV 1.9 generation context.');
        }

        if (
            $invoice->customer->taxIdentifier->value !== $context->customer->identification
            || $invoice->customer->name !== $context->customer->name
        ) {
            throw new InvalidArgumentException('Invoice customer does not match the enriched FEV 1.9 customer.');
        }

        if (count($invoice->lines) !== count($context->lineUnitCodes)) {
            throw new InvalidArgumentException('Exactly one unit code is required for each invoice line.');
        }

        $calculated = $this->invoiceCalculator->calculate($invoice, $context->calculationPolicy);
        $currency = $calculated->lineExtensionAmount->currency;
        $taxTotals = $this->taxTotals($calculated->taxes, $context);
        $taxAmounts = $this->cufeTaxAmounts($taxTotals, $currency);
        $issueDate = $context->issuedAt->format('Y-m-d');
        $issueTime = $context->issuedAt->format('H:i:sP');

        $cufe = $this->cufeCalculator->calculate(new CufeInput(
            invoiceNumber: $invoice->number,
            issueDate: $issueDate,
            issueTime: $issueTime,
            lineExtensionAmount: $calculated->lineExtensionAmount->amount,
            vatAmount: $taxAmounts['01'],
            incAmount: $taxAmounts['04'],
            icaAmount: $taxAmounts['03'],
            payableAmount: $calculated->payableAmount->amount,
            issuerTaxId: $context->supplier->identification,
            buyerIdentification: $context->customer->identification,
            technicalKey: $context->technicalKey(),
            environment: $context->environment,
        ));

        $lines = [];

        foreach ($invoice->lines as $index => $line) {
            $calculatedLine = $calculated->lines[$index];
            $lines[] = new InvoiceLine(
                id: (string) ($index + 1),
                quantity: $line->quantity->value,
                unitCode: $context->lineUnitCodes[$index],
                lineExtensionAmount: $calculatedLine->lineExtensionAmount->amount,
                description: $line->description,
                priceAmount: DecimalArithmetic::quantize(
                    $line->unitPrice->amount,
                    2,
                    $context->calculationPolicy->roundingMode,
                ),
                baseQuantity: '1',
                taxes: $this->taxTotals($calculatedLine->taxes, $context),
            );
        }

        return new InvoiceDocument(
            environment: $context->environment,
            control: $context->control,
            softwareProvider: $context->softwareCredentials->providerFor($invoice->number, $this->softwareCalculator),
            customizationId: $context->customizationId,
            invoiceNumber: $invoice->number,
            cufe: $cufe,
            issueDate: $issueDate,
            issueTime: $issueTime,
            invoiceTypeCode: $context->invoiceTypeCode,
            currency: $currency,
            supplier: $context->supplier,
            customer: $context->customer,
            paymentMeansId: $context->paymentMeansId,
            paymentMeansCode: $context->paymentMeansCode,
            paymentDueDate: $context->paymentDueDate,
            taxes: $taxTotals,
            totals: new InvoiceMonetaryTotal(
                lineExtensionAmount: $calculated->lineExtensionAmount->amount,
                taxExclusiveAmount: $calculated->taxExclusiveAmount->amount,
                taxInclusiveAmount: $calculated->taxInclusiveAmount->amount,
                payableAmount: $calculated->payableAmount->amount,
            ),
            lines: $lines,
        );
    }

    /**
     * @param list<CalculatedTax> $taxes
     * @return list<InvoiceTaxTotal>
     */
    private function taxTotals(array $taxes, InvoiceGenerationContext $context): array
    {
        /** @var array<string, array{amount: Money, subtotals: list<InvoiceTaxSubtotal>}> $groups */
        $groups = [];

        foreach ($taxes as $tax) {
            $mapping = $context->taxMappingFor($tax->type);
            $groups[$mapping->dianId] ??= [
                'amount' => new Money('0.00', $tax->taxAmount->currency),
                'subtotals' => [],
            ];
            $groups[$mapping->dianId]['amount'] = $groups[$mapping->dianId]['amount']->plus($tax->taxAmount);
            $groups[$mapping->dianId]['subtotals'][] = new InvoiceTaxSubtotal(
                taxableAmount: $tax->taxableAmount->amount,
                taxAmount: $tax->taxAmount->amount,
                percent: $tax->rate->percent,
                taxSchemeId: $mapping->dianId,
                taxSchemeName: $mapping->dianName,
            );
        }

        $totals = [];

        foreach ($groups as $group) {
            if ($group['subtotals'] === []) {
                throw new LogicException('A mapped tax group cannot exist without subtotals.');
            }

            $totals[] = new InvoiceTaxTotal($group['amount']->amount, $group['subtotals']);
        }

        return $totals;
    }

    /**
     * @param list<InvoiceTaxTotal> $taxTotals
     * @return array{'01': string, '04': string, '03': string}
     */
    private function cufeTaxAmounts(array $taxTotals, string $currency): array
    {
        $amounts = [
            '01' => new Money('0.00', $currency),
            '04' => new Money('0.00', $currency),
            '03' => new Money('0.00', $currency),
        ];

        foreach ($taxTotals as $total) {
            $schemeId = $total->subtotals[0]->taxSchemeId;

            if (isset($amounts[$schemeId])) {
                $amounts[$schemeId] = $amounts[$schemeId]->plus(new Money($total->taxAmount, $currency));
            }
        }

        return [
            '01' => $amounts['01']->amount,
            '04' => $amounts['04']->amount,
            '03' => $amounts['03']->amount,
        ];
    }
}
