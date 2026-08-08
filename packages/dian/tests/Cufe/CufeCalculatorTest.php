<?php

declare(strict_types=1);

namespace Tribux\Dian\Tests\Cufe;

use InvalidArgumentException;
use JsonException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tribux\Dian\Cufe\CufeCalculator;
use Tribux\Dian\Cufe\CufeInput;
use Tribux\Dian\DianEnvironment;

final class CufeCalculatorTest extends TestCase
{
    #[Test]
    public function it_matches_the_official_fev_1_9_invoice_example(): void
    {
        $fixture = $this->fixture('invoice-sale-positive.json');
        $input = $fixture['input'];
        self::assertIsArray($input);

        $cufeInput = $this->inputFrom($input);
        $calculator = new CufeCalculator();

        self::assertSame($fixture['canonical_string'], $calculator->canonicalString($cufeInput));
        self::assertSame($fixture['expected_cufe'], $calculator->calculate($cufeInput));
    }

    #[Test]
    public function it_rejects_a_non_canonical_amount_fixture(): void
    {
        $fixture = $this->fixture('invoice-sale-invalid-amount.json');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage((string) $fixture['expected_error']);

        new CufeInput(
            invoiceNumber: '323200000129',
            issueDate: '2019-01-16',
            issueTime: '10:53:10-05:00',
            lineExtensionAmount: (string) $fixture['invalid_value'],
            vatAmount: '285000.00',
            incAmount: '0.00',
            icaAmount: '0.00',
            payableAmount: '1785000.00',
            issuerTaxId: '700085371',
            buyerIdentification: '800199436',
            technicalKey: '693ff6f2a553c3646a063436fd4dd9ded0311471',
            environment: DianEnvironment::Production,
        );
    }

    /** @return array<string, mixed> */
    private function fixture(string $name): array
    {
        $json = file_get_contents(__DIR__.'/../Fixtures/fev-1.9/cufe/'.$name);
        self::assertIsString($json);

        try {
            $fixture = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            self::fail($exception->getMessage());
        }

        self::assertIsArray($fixture);

        return $fixture;
    }

    /** @param array<string, mixed> $input */
    private function inputFrom(array $input): CufeInput
    {
        return new CufeInput(
            invoiceNumber: (string) $input['invoice_number'],
            issueDate: (string) $input['issue_date'],
            issueTime: (string) $input['issue_time'],
            lineExtensionAmount: (string) $input['line_extension_amount'],
            vatAmount: (string) $input['vat_amount'],
            incAmount: (string) $input['inc_amount'],
            icaAmount: (string) $input['ica_amount'],
            payableAmount: (string) $input['payable_amount'],
            issuerTaxId: (string) $input['issuer_tax_id'],
            buyerIdentification: (string) $input['buyer_identification'],
            technicalKey: (string) $input['technical_key'],
            environment: DianEnvironment::from((string) $input['environment']),
        );
    }
}
