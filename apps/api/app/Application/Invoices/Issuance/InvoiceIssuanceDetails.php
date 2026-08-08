<?php

declare(strict_types=1);

namespace App\Application\Invoices\Issuance;

use DateTimeImmutable;
use InvalidArgumentException;
use Tribux\Dian\Documents\Fev19\Invoice\InvoiceParty;

/**
 * The half of an InvoiceGenerationContext that belongs to the request rather
 * than to the issuer.
 *
 * Splitting it this way keeps the boundary honest: the issuer profile owns
 * everything that is stable for an issuer, and this owns everything that is
 * specific to one document. The build stage joins them with the reserved
 * number and the issuer secrets.
 */
final readonly class InvoiceIssuanceDetails
{
    /** @var non-empty-list<string> */
    public array $lineUnitCodes;

    /** @param list<string> $lineUnitCodes one per invoice line, in order */
    public function __construct(
        public InvoiceParty $customer,
        public DateTimeImmutable $issuedAt,
        public string $paymentMeansId,
        public string $paymentMeansCode,
        public string $paymentDueDate,
        array $lineUnitCodes,
    ) {
        if ($lineUnitCodes === []) {
            throw new InvalidArgumentException('At least one line unit code is required.');
        }

        $this->lineUnitCodes = $lineUnitCodes;
    }
}
