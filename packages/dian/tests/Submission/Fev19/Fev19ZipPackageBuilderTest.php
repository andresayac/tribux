<?php

declare(strict_types=1);

namespace Tribux\Dian\Tests\Submission\Fev19;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tribux\Dian\Documents\DianDocumentType;
use Tribux\Dian\Submission\Fev19\Fev19FileNameGenerator;
use Tribux\Dian\Submission\Fev19\Fev19FileSequence;
use Tribux\Dian\Submission\Fev19\Fev19SubmissionDocument;
use Tribux\Dian\Submission\Fev19\Fev19SubmissionMode;
use Tribux\Dian\Submission\Fev19\Fev19ZipPackageBuilder;
use ZipArchive;

final class Fev19ZipPackageBuilderTest extends TestCase
{
    #[Test]
    public function it_builds_a_deterministic_mixed_asynchronous_zip(): void
    {
        $names = new Fev19FileNameGenerator('800197268', '000', 2019);
        $invoice = new Fev19SubmissionDocument(
            DianDocumentType::Invoice,
            $names->documentName(DianDocumentType::Invoice, new Fev19FileSequence('00000001')),
            '<Invoice>one</Invoice>',
        );
        $creditNote = new Fev19SubmissionDocument(
            DianDocumentType::CreditNote,
            $names->documentName(DianDocumentType::CreditNote, new Fev19FileSequence('00000002')),
            '<CreditNote>two</CreditNote>',
        );
        $zipName = $names->zipName(new Fev19FileSequence('00000003'));
        $builder = new Fev19ZipPackageBuilder();

        $first = $builder->build($zipName, Fev19SubmissionMode::Asynchronous, $invoice, $creditNote);
        $second = $builder->build($zipName, Fev19SubmissionMode::Asynchronous, $creditNote, $invoice);

        self::assertSame($zipName, $first->fileName);
        self::assertSame(Fev19SubmissionMode::Asynchronous, $first->mode);
        self::assertSame([$invoice->fileName, $creditNote->fileName], $first->documentFileNames);
        self::assertSame($first->documentFileNames, $second->documentFileNames);
        self::assertSame($first->contents, $second->contents);
        self::assertSame([
            $invoice->fileName => $invoice->xml,
            $creditNote->fileName => $creditNote->xml,
        ], $this->archiveEntries($first->contents));
    }

    #[Test]
    public function it_builds_a_synchronous_zip_with_exactly_one_document(): void
    {
        $names = new Fev19FileNameGenerator('800197268', '000', 2019);
        $invoice = new Fev19SubmissionDocument(
            DianDocumentType::Invoice,
            $names->documentName(DianDocumentType::Invoice, new Fev19FileSequence('00000001')),
            '<Invoice/>',
        );

        $package = (new Fev19ZipPackageBuilder())->build(
            $names->zipName(new Fev19FileSequence('00000001')),
            Fev19SubmissionMode::Synchronous,
            $invoice,
        );

        self::assertSame([$invoice->fileName => '<Invoice/>'], $this->archiveEntries($package->contents));
    }

    #[Test]
    public function it_enforces_sync_and_async_cardinality(): void
    {
        $names = new Fev19FileNameGenerator('800197268', '000', 2019);
        $zipName = $names->zipName(new Fev19FileSequence('00000001'));
        $documents = [];

        for ($position = 1; $position <= 51; ++$position) {
            $documents[] = new Fev19SubmissionDocument(
                DianDocumentType::Invoice,
                $names->documentName(DianDocumentType::Invoice, new Fev19FileSequence(sprintf('%08X', $position))),
                '<Invoice/>',
            );
        }

        $builder = new Fev19ZipPackageBuilder();

        try {
            $builder->build($zipName, Fev19SubmissionMode::Asynchronous);
            self::fail('Expected an empty asynchronous package to fail.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('between one and 50', $exception->getMessage());
        }

        try {
            $builder->build($zipName, Fev19SubmissionMode::Synchronous, $documents[0], $documents[1]);
            self::fail('Expected a synchronous package with two documents to fail.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('exactly one', $exception->getMessage());
        }

        try {
            $builder->build($zipName, Fev19SubmissionMode::Asynchronous, ...$documents);
            self::fail('Expected an asynchronous package with 51 documents to fail.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('between one and 50', $exception->getMessage());
        }

        $maximumPackage = $builder->build(
            $zipName,
            Fev19SubmissionMode::Asynchronous,
            ...array_slice($documents, 0, 50),
        );

        self::assertCount(50, $maximumPackage->documentFileNames);
    }

    #[Test]
    public function it_rejects_duplicate_names_and_cross_issuer_packages(): void
    {
        $names = new Fev19FileNameGenerator('800197268', '000', 2019);
        $otherNames = new Fev19FileNameGenerator('900999888', '000', 2019);
        $sequence = new Fev19FileSequence('00000001');
        $document = new Fev19SubmissionDocument(
            DianDocumentType::Invoice,
            $names->documentName(DianDocumentType::Invoice, $sequence),
            '<Invoice/>',
        );
        $builder = new Fev19ZipPackageBuilder();

        try {
            $builder->build($names->zipName($sequence), Fev19SubmissionMode::Asynchronous, $document, $document);
            self::fail('Expected duplicate archive entries to fail.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('duplicate', $exception->getMessage());
        }

        try {
            $builder->build($otherNames->zipName($sequence), Fev19SubmissionMode::Asynchronous, $document);
            self::fail('Expected a cross-issuer package to fail.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('same issuer', $exception->getMessage());
        }
    }

    #[Test]
    public function it_rejects_invalid_document_metadata(): void
    {
        try {
            new Fev19SubmissionDocument(DianDocumentType::Invoice, '../invoice.xml', '<Invoice/>');
            self::fail('Expected a path-like filename to fail.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('naming structure', $exception->getMessage());
        }

        try {
            new Fev19SubmissionDocument(
                DianDocumentType::CreditNote,
                'fv08001972680001900000001.xml',
                '<Invoice/>',
            );
            self::fail('Expected a mismatched document type to fail.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('document type', $exception->getMessage());
        }
    }

    /** @return array<string, string> */
    private function archiveEntries(string $contents): array
    {
        $path = tempnam(sys_get_temp_dir(), 'tribux-zip-test-');

        if (! is_string($path) || file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Could not prepare ZIP test fixture.');
        }

        $archive = new ZipArchive();

        try {
            if ($archive->open($path) !== true) {
                throw new RuntimeException('Could not open generated ZIP in test.');
            }

            $entries = [];

            for ($index = 0; $index < $archive->numFiles; ++$index) {
                $name = $archive->getNameIndex($index);

                if (! is_string($name)) {
                    throw new RuntimeException('Could not read generated ZIP entry name.');
                }

                $entryContents = $archive->getFromIndex($index);

                if (! is_string($entryContents)) {
                    throw new RuntimeException('Could not read generated ZIP entry.');
                }

                $entries[$name] = $entryContents;
            }

            return $entries;
        } finally {
            $archive->close();

            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
