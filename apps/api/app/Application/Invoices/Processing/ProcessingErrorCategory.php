<?php

declare(strict_types=1);

namespace App\Application\Invoices\Processing;

/**
 * Local classification of why an attempt stopped. See ADR 0016.
 *
 * The category is Tribux vocabulary, not a DIAN taxonomy: DIAN codes and
 * messages are preserved verbatim next to it.
 */
enum ProcessingErrorCategory: string
{
    case InputValidation = 'input_validation';
    case Configuration = 'configuration';
    case LocalValidation = 'local_validation';
    case Signing = 'signing';
    case Packaging = 'packaging';
    case TransportSafe = 'transport_safe';
    case TransportAmbiguous = 'transport_ambiguous';
    case SoapProtocol = 'soap_protocol';
    case DianBusiness = 'dian_business';
    case Internal = 'internal';

    /**
     * Only failures proven to have happened before the request bytes reached
     * DIAN may be repeated without asking DIAN first. Anything else — including
     * a total timeout — could have been received and processed remotely.
     */
    public function isAutomaticallyRetryable(): bool
    {
        return match ($this) {
            self::TransportSafe, self::Internal => true,
            default => false,
        };
    }
}
