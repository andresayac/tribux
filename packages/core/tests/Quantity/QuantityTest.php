<?php

declare(strict_types=1);

namespace Tribux\Core\Tests\Quantity;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tribux\Core\Quantity\Quantity;

final class QuantityTest extends TestCase
{
    #[Test]
    public function it_rejects_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Quantity('0.000');
    }
}
