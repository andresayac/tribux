<?php

declare(strict_types=1);

namespace Tribux\Dian\Documents\Fev19\Invoice;

use InvalidArgumentException;
use Tribux\Dian\DianEnvironment;
use Tribux\Dian\Documents\Fev19\Support\Fev19Value;

final readonly class InvoiceDocument
{
    public const PROFILE_ID = 'DIAN 2.1: Factura Electrónica de Venta';

    /**
     * @param list<InvoiceTaxTotal> $taxes
     * @param non-empty-list<InvoiceLine> $lines
     */
    public function __construct(
        public DianEnvironment $environment,
        public InvoiceControl $control,
        public SoftwareProvider $softwareProvider,
        public string $customizationId,
        public string $invoiceNumber,
        public string $cufe,
        public string $issueDate,
        public string $issueTime,
        public string $invoiceTypeCode,
        public string $currency,
        public InvoiceParty $supplier,
        public InvoiceParty $customer,
        public string $paymentMeansId,
        public string $paymentMeansCode,
        public string $paymentDueDate,
        public array $taxes,
        public InvoiceMonetaryTotal $totals,
        public array $lines,
    ) {
        Fev19Value::code($customizationId, 'customizationId');
        Fev19Value::text($invoiceNumber, 'invoiceNumber');
        Fev19Value::sha384($cufe, 'cufe');
        Fev19Value::date($issueDate, 'issueDate');
        Fev19Value::time($issueTime, 'issueTime');
        Fev19Value::code($invoiceTypeCode, 'invoiceTypeCode');
        Fev19Value::currency($currency);
        Fev19Value::code($paymentMeansId, 'paymentMeansId');
        Fev19Value::code($paymentMeansCode, 'paymentMeansCode');
        Fev19Value::date($paymentDueDate, 'paymentDueDate');

        if ($lines === []) {
            throw new InvalidArgumentException('lines must contain at least one invoice line.');
        }

        foreach ($taxes as $tax) {
            if (! $tax instanceof InvoiceTaxTotal) {
                throw new InvalidArgumentException('taxes must contain InvoiceTaxTotal values only.');
            }
        }

        foreach ($lines as $line) {
            if (! $line instanceof InvoiceLine) {
                throw new InvalidArgumentException('lines must contain InvoiceLine values only.');
            }
        }
    }
}
