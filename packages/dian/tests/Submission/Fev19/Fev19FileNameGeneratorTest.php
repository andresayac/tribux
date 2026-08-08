<?php

declare(strict_types=1);

namespace Tribux\Dian\Tests\Submission\Fev19;

use InvalidArgumentException;
use JsonException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tribux\Dian\Documents\DianDocumentType;
use Tribux\Dian\Submission\Fev19\Fev19FileNameGenerator;
use Tribux\Dian\Submission\Fev19\Fev19FileSequence;

final class Fev19FileNameGeneratorTest extends TestCase
{
    #[Test]
    public function it_reproduces_the_official_literal_naming_example(): void
    {
        $fixture = $this->fixture();
        $generator = new Fev19FileNameGenerator(
            $fixture['issuerTaxId'],
            $fixture['providerCode'],
            $fixture['calendarYear'],
        );
        $sequence = new Fev19FileSequence($fixture['officialLiteralSequence']);

        self::assertSame(
            $fixture['expectedInvoiceNameFromOfficialLiteral'],
            $generator->documentName(DianDocumentType::Invoice, $sequence),
        );
        self::assertSame($fixture['expectedZipNameFromOfficialLiteral'], $generator->zipName($sequence));
    }

    #[Test]
    public function it_accepts_an_exact_hexadecimal_token_without_inferring_its_ordinal(): void
    {
        $fixture = $this->fixture();
        $generator = new Fev19FileNameGenerator(
            $fixture['issuerTaxId'],
            $fixture['providerCode'],
            $fixture['calendarYear'],
        );
        $sequence = new Fev19FileSequence($fixture['hexadecimalElevenSequence']);

        self::assertSame(
            'fv0800197268000190000000B.xml',
            $generator->documentName(DianDocumentType::Invoice, $sequence),
        );
        self::assertSame('0000000B', $sequence->encoded);
    }

    #[Test]
    public function it_maps_every_document_type_to_the_annex_prefix(): void
    {
        $generator = new Fev19FileNameGenerator('1', '001', 2026);
        $sequence = new Fev19FileSequence('00000001');

        self::assertSame('fv00000000010012600000001.xml', $generator->documentName(DianDocumentType::Invoice, $sequence));
        self::assertSame('nc00000000010012600000001.xml', $generator->documentName(DianDocumentType::CreditNote, $sequence));
        self::assertSame('nd00000000010012600000001.xml', $generator->documentName(DianDocumentType::DebitNote, $sequence));
        self::assertSame('ar00000000010012600000001.xml', $generator->documentName(DianDocumentType::ApplicationResponse, $sequence));
        self::assertSame('ad00000000010012600000001.xml', $generator->documentName(DianDocumentType::AttachedDocument, $sequence));
    }

    #[Test]
    public function it_rejects_noncanonical_or_out_of_range_sequence_tokens(): void
    {
        foreach ($this->fixture()['invalidSequences'] as $invalidSequence) {
            try {
                new Fev19FileSequence($invalidSequence);
                self::fail('Expected invalid sequence to be rejected: '.$invalidSequence);
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('uppercase hexadecimal token', $exception->getMessage());
            }
        }
    }

    #[Test]
    public function it_rejects_invalid_name_components(): void
    {
        foreach ([
            ['800.197.268', '000', 2019, 'Issuer tax ID'],
            ['12345678901', '000', 2019, 'Issuer tax ID'],
            ['800197268', '00', 2019, 'provider code'],
            ['800197268', 'ABC', 2019, 'provider code'],
            ['800197268', '000', 99, 'Calendar year'],
        ] as [$taxId, $providerCode, $year, $message]) {
            try {
                new Fev19FileNameGenerator($taxId, $providerCode, $year);
                self::fail('Expected invalid filename component to be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString($message, $exception->getMessage());
            }
        }
    }

    /**
     * @return array{
     *   issuerTaxId:string,
     *   providerCode:string,
     *   calendarYear:int,
     *   officialLiteralSequence:string,
     *   hexadecimalElevenSequence:string,
     *   expectedInvoiceNameFromOfficialLiteral:string,
     *   expectedZipNameFromOfficialLiteral:string,
     *   invalidSequences:list<string>
     * }
     */
    private function fixture(): array
    {
        $contents = file_get_contents(__DIR__.'/../../Fixtures/fev-1.9/submission/naming.json');
        self::assertIsString($contents);

        try {
            /** @var array{
             *   issuerTaxId:string,
             *   providerCode:string,
             *   calendarYear:int,
             *   officialLiteralSequence:string,
             *   hexadecimalElevenSequence:string,
             *   expectedInvoiceNameFromOfficialLiteral:string,
             *   expectedZipNameFromOfficialLiteral:string,
             *   invalidSequences:list<string>
             * } $fixture
             */
            $fixture = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            self::fail($exception->getMessage());
        }

        return $fixture;
    }
}
