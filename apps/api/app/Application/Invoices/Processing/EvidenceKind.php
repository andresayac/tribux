<?php

declare(strict_types=1);

namespace App\Application\Invoices\Processing;

/**
 * Artefacts worth keeping to audit an attempt. See ADR 0016.
 *
 * A P12/PFX, a private key, a certificate password, the software PIN and the
 * technical key are never evidence and have no kind here.
 */
enum EvidenceKind: string
{
    case UnsignedXml = 'unsigned_xml';
    case XsdUnsignedResult = 'xsd_unsigned_result';
    case SchematronResult = 'schematron_result';
    case SignedXml = 'signed_xml';
    case XsdSignedResult = 'xsd_signed_result';
    case SubmissionZip = 'submission_zip';
    case SendTestSetRequestXml = 'send_test_set_request_xml';
    case SendTestSetResponseXml = 'send_test_set_response_xml';
    case GetStatusZipResponseXml = 'get_status_zip_response_xml';
    case SoapFaultDetail = 'soap_fault_detail';

    /**
     * A SOAP request carries the whole signed document, so it contains taxpayer
     * data. Storing it is opt-in, never a default.
     */
    public function requiresExplicitOptIn(): bool
    {
        return $this === self::SendTestSetRequestXml;
    }
}
