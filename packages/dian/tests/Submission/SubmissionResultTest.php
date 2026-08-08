<?php

declare(strict_types=1);

namespace Tribux\Dian\Tests\Submission;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tribux\Dian\Submission\SubmissionResult;

final class SubmissionResultTest extends TestCase
{
    #[Test]
    public function it_preserves_structured_dian_messages(): void
    {
        $result = new SubmissionResult(
            accepted: false,
            externalReference: 'track-id',
            messages: [['code' => 'DIAN-CODE', 'message' => 'Original message']],
        );

        self::assertFalse($result->accepted);
        self::assertSame('DIAN-CODE', $result->messages[0]['code']);
        self::assertSame('Original message', $result->messages[0]['message']);
    }
}
