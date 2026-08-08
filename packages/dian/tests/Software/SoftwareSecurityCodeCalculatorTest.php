<?php

declare(strict_types=1);

namespace Tribux\Dian\Tests\Software;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tribux\Dian\Software\SoftwareSecurityCodeCalculator;

final class SoftwareSecurityCodeCalculatorTest extends TestCase
{
    #[Test]
    public function it_calculates_the_fev_1_9_security_code_without_separators(): void
    {
        $calculator = new SoftwareSecurityCodeCalculator();

        self::assertSame(
            'd57afa74725d6766c300677799962d0fec2ef422ac71733dd74a4884e02d0ab8cff8451726e5020450265d049dd6a6ba',
            $calculator->calculate('00000000-0000-4000-8000-000000000001', '12345', 'SETP1'),
        );
    }

    #[Test]
    public function it_rejects_an_empty_pin(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('pin cannot be empty.');

        (new SoftwareSecurityCodeCalculator())->calculate('software-id', '', 'SETP1');
    }
}
