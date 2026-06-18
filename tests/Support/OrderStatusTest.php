<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Tests\Support;

use Baldie81\Magento2SDK\Enum\OrderState;
use Baldie81\Magento2SDK\Support\OrderStatus;
use PHPUnit\Framework\TestCase;

final class OrderStatusTest extends TestCase
{
    protected function tearDown(): void
    {
        // The registry is process-wide; keep tests isolated.
        OrderStatus::reset();
    }

    public function testDefaultStatusIsKnownWithLabelAndState(): void
    {
        $status = OrderStatus::of(OrderStatus::PROCESSING);

        self::assertSame('processing', $status->code());
        self::assertSame('Processing', $status->label());
        self::assertTrue($status->isKnown());
        self::assertSame(OrderState::Processing, $status->state());
    }

    public function testUnknownStatusFallsBackToHumanizedLabelAndNullState(): void
    {
        $status = OrderStatus::of('awaiting_pickup');

        self::assertFalse($status->isKnown());
        self::assertSame('Awaiting Pickup', $status->label());
        self::assertNull($status->state());
    }

    public function testRegisterMakesACustomStatusKnown(): void
    {
        OrderStatus::register('awaiting_pickup', 'Ready for Collection');

        $status = OrderStatus::of('awaiting_pickup');
        self::assertTrue($status->isKnown());
        self::assertSame('Ready for Collection', $status->label());
        self::assertArrayHasKey('awaiting_pickup', OrderStatus::known());
    }

    public function testRegisterManyAcceptsMapOrList(): void
    {
        OrderStatus::registerMany(['in_transit' => 'In Transit', 'delivered' => null]);
        OrderStatus::registerMany(['returned']);

        self::assertSame('In Transit', OrderStatus::of('in_transit')->label());
        self::assertSame('Delivered', OrderStatus::of('delivered')->label()); // null label -> humanized
        self::assertTrue(OrderStatus::of('returned')->isKnown());
    }

    public function testUnregisterAndResetRestoreDefaults(): void
    {
        OrderStatus::register('awaiting_pickup');
        OrderStatus::unregister(OrderStatus::FRAUD);

        self::assertTrue(OrderStatus::of('awaiting_pickup')->isKnown());
        self::assertFalse(OrderStatus::of(OrderStatus::FRAUD)->isKnown());

        OrderStatus::reset();

        self::assertFalse(OrderStatus::of('awaiting_pickup')->isKnown());
        self::assertTrue(OrderStatus::of(OrderStatus::FRAUD)->isKnown());
    }

    public function testEqualsAndStringCast(): void
    {
        $status = OrderStatus::of(OrderStatus::COMPLETE);

        self::assertTrue($status->equals('complete'));
        self::assertTrue($status->equals(OrderStatus::of('complete')));
        self::assertFalse($status->equals('closed'));
        self::assertSame('complete', (string) $status);
    }
}
