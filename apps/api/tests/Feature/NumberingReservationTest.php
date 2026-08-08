<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Invoices\CreateInvoice;
use App\Application\Invoices\Numbering\Contracts\DocumentSequenceReserver;
use App\Application\Invoices\Numbering\Contracts\InvoiceNumberReserver;
use App\Application\Invoices\Numbering\DocumentSequenceScope;
use App\Application\Issuers\Contracts\IssuerProfileProvider;
use App\Infrastructure\Issuers\JsonFileIssuerProfileProvider;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\InvoicePayload;
use Tests\TestCase;
use Tribux\Core\Numbering\AuthorizationNotActive;
use Tribux\Core\Numbering\NumberingAuthorization;
use Tribux\Core\Numbering\NumberOutsideAuthorizedRange;

final class NumberingReservationTest extends TestCase
{
    use RefreshDatabase;

    private const string ISSUER = 'issuer_demo';

    public function test_it_starts_at_the_first_authorized_number(): void
    {
        $invoiceId = $this->createInvoice('numbering-first');

        $reserved = $this->reserver()->reserve(self::ISSUER, $invoiceId, $this->authorization(), $this->moment());

        self::assertSame(1, $reserved->ordinal);
        self::assertSame('SETP1', $reserved->value);
        self::assertSame('18760000001', $reserved->authorizationReference);
    }

    public function test_consecutive_invoices_never_share_a_number(): void
    {
        $values = [];

        foreach (range(1, 5) as $index) {
            $invoiceId = $this->createInvoice('numbering-series-'.$index);
            $values[] = $this->reserver()
                ->reserve(self::ISSUER, $invoiceId, $this->authorization(), $this->moment())
                ->ordinal;
        }

        self::assertSame([1, 2, 3, 4, 5], $values);
        self::assertSame($values, array_values(array_unique($values)));
    }

    public function test_reserving_twice_for_one_invoice_returns_the_same_number(): void
    {
        $invoiceId = $this->createInvoice('numbering-idempotent');
        $reserver = $this->reserver();

        $first = $reserver->reserve(self::ISSUER, $invoiceId, $this->authorization(), $this->moment());
        $second = $reserver->reserve(self::ISSUER, $invoiceId, $this->authorization(), $this->moment());

        self::assertSame($first->ordinal, $second->ordinal);
        self::assertSame(1, DB::table('invoice_number_reservations')->count());
        self::assertSame($first->ordinal, $reserver->find($invoiceId)?->ordinal);
    }

    public function test_it_skips_a_number_another_writer_already_took(): void
    {
        // Stands in for the loser of a race: the ordinal it computed is gone by
        // the time it inserts, so it must move on rather than fail.
        $taken = $this->createInvoice('numbering-race-winner');
        $this->reserver()->reserve(self::ISSUER, $taken, $this->authorization(), $this->moment());

        $late = $this->createInvoice('numbering-race-loser');
        $reserved = $this->reserver()->reserve(self::ISSUER, $late, $this->authorization(), $this->moment());

        self::assertSame(2, $reserved->ordinal);
    }

    public function test_the_database_refuses_the_same_number_twice(): void
    {
        $invoiceId = $this->createInvoice('numbering-unique-index');
        $this->reserver()->reserve(self::ISSUER, $invoiceId, $this->authorization(), $this->moment());
        $other = $this->createInvoice('numbering-unique-index-other');

        DB::beginTransaction();

        try {
            DB::table('invoice_number_reservations')->insert([
                'id' => '019fe2ad-0000-7000-8000-0000000000ff',
                'issuer_id' => self::ISSUER,
                'authorization_reference' => '18760000001',
                'prefix' => 'SETP',
                'ordinal' => 1,
                'value' => 'SETP1',
                'invoice_id' => $other,
                'reserved_at' => '2026-08-08 12:00:00+00',
            ]);

            DB::rollBack();
            self::fail('The unique index must refuse a repeated number.');
        } catch (QueryException $exception) {
            DB::rollBack();
            self::assertStringContainsStringIgnoringCase('unique', $exception->getMessage());
        }

        self::assertSame(1, DB::table('invoice_number_reservations')->count());
    }

    public function test_a_consumed_number_is_never_returned_to_the_pool(): void
    {
        $failed = $this->createInvoice('numbering-consumed');
        $this->reserver()->reserve(self::ISSUER, $failed, $this->authorization(), $this->moment());

        // Whatever happens to that invoice afterwards, the next one moves on.
        $next = $this->createInvoice('numbering-after-failure');
        $reserved = $this->reserver()->reserve(self::ISSUER, $next, $this->authorization(), $this->moment());

        self::assertSame(2, $reserved->ordinal);
    }

    public function test_it_refuses_to_issue_past_the_end_of_the_range(): void
    {
        $authorization = new NumberingAuthorization(
            reference: '18760000002',
            prefix: 'SETP',
            from: 1,
            to: 1,
            validFrom: new DateTimeImmutable('2026-01-01'),
            validTo: new DateTimeImmutable('2027-12-31'),
        );

        $first = $this->createInvoice('numbering-exhausted-first');
        $this->reserver()->reserve(self::ISSUER, $first, $authorization, $this->moment());
        $second = $this->createInvoice('numbering-exhausted-second');

        $this->expectException(NumberOutsideAuthorizedRange::class);
        $this->expectExceptionMessage('no numbers left');

        $this->reserver()->reserve(self::ISSUER, $second, $authorization, $this->moment());
    }

    public function test_it_refuses_to_issue_outside_the_authorization_validity(): void
    {
        $invoiceId = $this->createInvoice('numbering-expired');

        $this->expectException(AuthorizationNotActive::class);

        $this->reserver()->reserve(
            self::ISSUER,
            $invoiceId,
            $this->authorization(),
            new DateTimeImmutable('2028-01-02T10:00:00-05:00'),
        );
    }

    public function test_xml_and_zip_sequences_are_counted_separately(): void
    {
        $sequences = $this->sequences();
        $first = $this->owner(1);
        $second = $this->owner(2);

        self::assertSame(1, $sequences->reserve(self::ISSUER, DocumentSequenceScope::Xml, 2026, $first));
        self::assertSame(1, $sequences->reserve(self::ISSUER, DocumentSequenceScope::Zip, 2026, $first));
        self::assertSame(2, $sequences->reserve(self::ISSUER, DocumentSequenceScope::Xml, 2026, $second));
    }

    public function test_a_sequence_restarts_each_calendar_year(): void
    {
        $sequences = $this->sequences();

        self::assertSame(1, $sequences->reserve(self::ISSUER, DocumentSequenceScope::Xml, 2026, $this->owner(3)));
        self::assertSame(1, $sequences->reserve(self::ISSUER, DocumentSequenceScope::Xml, 2027, $this->owner(4)));
    }

    public function test_a_sequence_is_idempotent_per_owner(): void
    {
        $sequences = $this->sequences();
        $owner = $this->owner(5);

        $first = $sequences->reserve(self::ISSUER, DocumentSequenceScope::Xml, 2026, $owner);
        $second = $sequences->reserve(self::ISSUER, DocumentSequenceScope::Xml, 2026, $owner);

        self::assertSame($first, $second);
        self::assertSame(1, DB::table('document_sequence_reservations')->count());
    }

    public function test_the_issuer_profile_exposes_its_authorized_range(): void
    {
        $profile = $this->app->make(IssuerProfileProvider::class)->get(self::ISSUER);

        self::assertSame('18760000001', $profile->numbering->reference);
        self::assertSame('SETP', $profile->numbering->prefix);
        self::assertSame(1, $profile->numbering->from);
        self::assertSame(5000000, $profile->numbering->to);
        self::assertTrue($profile->numbering->isActiveOn($profile->localise($this->moment())));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->singleton(
            IssuerProfileProvider::class,
            fn (): JsonFileIssuerProfileProvider => new JsonFileIssuerProfileProvider(
                __DIR__.'/../../../../examples/issuer.habilitation.json',
            ),
        );
    }

    private function reserver(): InvoiceNumberReserver
    {
        return $this->app->make(InvoiceNumberReserver::class);
    }

    private function sequences(): DocumentSequenceReserver
    {
        return $this->app->make(DocumentSequenceReserver::class);
    }

    private function authorization(): NumberingAuthorization
    {
        return $this->app->make(IssuerProfileProvider::class)->get(self::ISSUER)->numbering;
    }

    /** A sequence owner is always a Tribux UUID, and PostgreSQL enforces it. */
    private function owner(int $index): string
    {
        return sprintf('019fe2ad-0000-7000-8000-%012d', $index);
    }

    private function moment(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-08T10:30:00-05:00');
    }

    private function createInvoice(string $idempotencyKey): string
    {
        return $this->app->make(CreateInvoice::class)
            ->execute(InvoicePayload::minimal(), $idempotencyKey)
            ->invoice
            ->id;
    }
}
