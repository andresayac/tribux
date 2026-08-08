<?php

declare(strict_types=1);

namespace Tribux\Dian\Contracts;

use Tribux\Dian\Submission\SubmissionRequest;
use Tribux\Dian\Submission\SubmissionResult;

/**
 * Transport boundary.
 *
 * This contract intentionally does not expose SoapClient or WSDL types.
 *
 * @deprecated Predates the SOAP parsers and loses the evidence Tribux needs:
 * raw XML, HTTP status, SOAP Fault and the optional DianResponse fields. Use
 * the DIAN clients directly, or the application-level submission and status
 * ports described in ADR 0016. Scheduled for removal once those ports land.
 */
interface DianGateway
{
    public function submit(SubmissionRequest $request): SubmissionResult;
}
