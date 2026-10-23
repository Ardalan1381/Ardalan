<?php

namespace Tests\Unit;

use App\Support\Quantity;
use PHPUnit\Framework\TestCase;

class QuantityDisplayTest extends TestCase
{
    public function test_display_drops_trailing_zeros_and_groups_thousands(): void
    {
        $this->assertSame('2,000', Quantity::display('2000.0000'));
        $this->assertSame('12.5', Quantity::display('12.5000'));
        $this->assertSame('0.0125', Quantity::display('0.0125'));
        $this->assertSame('-3', Quantity::display('-3.0000'));
        $this->assertSame('0', Quantity::display(null));
    }
}
