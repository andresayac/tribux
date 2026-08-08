<?php

declare(strict_types=1);

namespace Tribux\Core\Tests\Invoice;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tribux\Core\Invoice\IllegalStatusTransition;
use Tribux\Core\Invoice\InvoiceStatus;
use Tribux\Core\Invoice\InvoiceStatusTransition;

final class InvoiceStatusTransitionTest extends TestCase
{
    #[Test]
    public function every_status_declares_its_allowed_targets(): void
    {
        foreach (InvoiceStatus::cases() as $status) {
            InvoiceStatusTransition::allowedFrom($status);
        }

        self::assertTrue(true, 'Every InvoiceStatus case is covered by the transition table.');
    }

    /** @return iterable<string, array{InvoiceStatus, InvoiceStatus}> */
    public static function allowedTransitions(): iterable
    {
        yield 'draft to queued' => [InvoiceStatus::Draft, InvoiceStatus::Queued];
        yield 'queued to building' => [InvoiceStatus::Queued, InvoiceStatus::Building];
        yield 'building to signed' => [InvoiceStatus::Building, InvoiceStatus::Signed];
        yield 'signed to submitted' => [InvoiceStatus::Signed, InvoiceStatus::Submitted];
        yield 'signed to awaiting reconciliation' => [InvoiceStatus::Signed, InvoiceStatus::AwaitingReconciliation];
        yield 'submitted to accepted' => [InvoiceStatus::Submitted, InvoiceStatus::Accepted];
        yield 'submitted to rejected' => [InvoiceStatus::Submitted, InvoiceStatus::Rejected];
        yield 'awaiting reconciliation to submitted' => [InvoiceStatus::AwaitingReconciliation, InvoiceStatus::Submitted];
        yield 'retryable failure back to queued' => [InvoiceStatus::RetryableFailure, InvoiceStatus::Queued];
    }

    #[Test]
    #[DataProvider('allowedTransitions')]
    public function it_allows_the_documented_transitions(InvoiceStatus $from, InvoiceStatus $to): void
    {
        self::assertTrue(InvoiceStatusTransition::isAllowed($from, $to));

        InvoiceStatusTransition::guard($from, $to);
    }

    /** @return iterable<string, array{InvoiceStatus, InvoiceStatus}> */
    public static function forbiddenTransitions(): iterable
    {
        yield 'queued cannot skip building' => [InvoiceStatus::Queued, InvoiceStatus::Signed];
        yield 'queued cannot submit' => [InvoiceStatus::Queued, InvoiceStatus::Submitted];
        yield 'building cannot submit' => [InvoiceStatus::Building, InvoiceStatus::Submitted];
        yield 'building cannot accept' => [InvoiceStatus::Building, InvoiceStatus::Accepted];
        yield 'signed cannot accept' => [InvoiceStatus::Signed, InvoiceStatus::Accepted];
        yield 'ambiguous submission cannot become retryable' => [InvoiceStatus::Signed, InvoiceStatus::Queued];
        yield 'submitted cannot be resent' => [InvoiceStatus::Submitted, InvoiceStatus::Queued];
        yield 'submitted cannot go back to signed' => [InvoiceStatus::Submitted, InvoiceStatus::Signed];
        yield 'awaiting reconciliation never resends' => [InvoiceStatus::AwaitingReconciliation, InvoiceStatus::Queued];
        yield 'accepted is terminal' => [InvoiceStatus::Accepted, InvoiceStatus::Rejected];
        yield 'rejected is terminal' => [InvoiceStatus::Rejected, InvoiceStatus::Queued];
        yield 'permanent failure is terminal' => [InvoiceStatus::PermanentFailure, InvoiceStatus::Queued];
        yield 'a status cannot repeat itself' => [InvoiceStatus::Building, InvoiceStatus::Building];
    }

    #[Test]
    #[DataProvider('forbiddenTransitions')]
    public function it_rejects_transitions_outside_the_table(InvoiceStatus $from, InvoiceStatus $to): void
    {
        self::assertFalse(InvoiceStatusTransition::isAllowed($from, $to));

        $this->expectException(IllegalStatusTransition::class);
        $this->expectExceptionMessage(sprintf('from "%s" to "%s"', $from->value, $to->value));

        InvoiceStatusTransition::guard($from, $to);
    }

    #[Test]
    public function only_the_three_outcome_statuses_are_terminal(): void
    {
        $terminal = array_values(array_filter(
            InvoiceStatus::cases(),
            static fn (InvoiceStatus $status): bool => InvoiceStatusTransition::isTerminal($status),
        ));

        self::assertSame(
            [InvoiceStatus::Accepted, InvoiceStatus::Rejected, InvoiceStatus::PermanentFailure],
            $terminal,
        );
    }

    #[Test]
    public function only_an_explicit_retryable_failure_may_be_retried(): void
    {
        foreach (InvoiceStatus::cases() as $status) {
            self::assertSame(
                $status === InvoiceStatus::RetryableFailure,
                InvoiceStatusTransition::isRetryable($status),
                sprintf('Status "%s" reported an unexpected retry policy.', $status->value),
            );
        }
    }
}
