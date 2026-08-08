<?php

declare(strict_types=1);

namespace Tribux\Dian\Contracts;

use Tribux\Dian\Submission\SubmissionRequest;
use Tribux\Dian\Submission\SubmissionResult;

/**
 * Transport boundary.
 *
 * This contract intentionally does not expose SoapClient or WSDL types.
 */
interface DianGateway
{
    public function submit(SubmissionRequest $request): SubmissionResult;
}
