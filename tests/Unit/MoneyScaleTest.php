<?php

namespace Tests\Unit;

use App\Accounting\Money;
use PHPUnit\Framework\TestCase;

/**
 * Money honours the scale it is configured with.
 *
 * FACTOR was a hardcoded 100 while scaleDigits() read config('accounting.
 * scale'), so the two disagreed the moment the setting was used: parse()
 * padded the fraction to the configured width and multiplied the whole part by
 * 100 anyway. At a scale of 3, "1.234" became 1 * 100 + 234 = 334 minor units
 * - a third of what it should be - and every amount in the system was wrong
 * with nothing to show for it.
 */
class MoneyScaleTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->setScale(2);

        parent::tearDown();
    }

    public function test_it_round_trips_at_the_default_scale(): void
    {
        $this->setScale(2);

        $this->assertSame('12.34', Money::of('12.34')->toDecimal());
        $this->assertSame('12.00', Money::of(12)->toDecimal());
        $this->assertSame(1234, Money::of('12.34')->minor);
    }

    public function test_it_round_trips_at_a_scale_of_three(): void
    {
        $this->setScale(3);

        $this->assertSame(1234, Money::of('1.234')->minor);
        $this->assertSame('1.234', Money::of('1.234')->toDecimal());
        $this->assertSame('2.000', Money::of(2)->toDecimal());
    }

    public function test_arithmetic_holds_at_a_scale_of_three(): void
    {
        $this->setScale(3);

        $sum = Money::of('1.111')->plus(Money::of('2.222'));

        $this->assertSame('3.333', $sum->toDecimal());
        $this->assertTrue($sum->minus(Money::of('3.333'))->isZero());
    }

    /**
     * Money reads the scale off config(), which needs a container behind it.
     * These tests do not boot the application, so the helper is given one.
     */
    private function setScale(int $digits): void
    {
        $config = new \Illuminate\Config\Repository([
            'accounting' => ['scale' => $digits, 'base_currency' => 'USD'],
        ]);

        $container = new \Illuminate\Container\Container();
        $container->instance('config', $config);

        \Illuminate\Container\Container::setInstance($container);
        \Illuminate\Support\Facades\Facade::setFacadeApplication($container);
    }
}
