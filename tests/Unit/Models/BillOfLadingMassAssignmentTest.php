<?php

namespace Tests\Unit\Models;

use App\Models\BillOfLading;
use PHPUnit\Framework\TestCase;

class BillOfLadingMassAssignmentTest extends TestCase
{
    public function test_freight_amount_can_be_mass_assigned(): void
    {
        $bill = new BillOfLading();

        $bill->fill([
            'freight_terms' => 'prepaid',
            'freight_amount' => 27784.02,
        ]);

        $this->assertSame('prepaid', $bill->freight_terms);
        $this->assertSame('27784.02', $bill->freight_amount);
        $this->assertContains('freight_amount', $bill->getFillable());
    }
}
