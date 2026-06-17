<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Tests\Support;

use Baldie81\Magento2SDK\Support\AttributeOption;
use PHPUnit\Framework\TestCase;

final class AttributeOptionTest extends TestCase
{
    public function testBareLabelOnlyEmitsLabel(): void
    {
        self::assertSame(['label' => 'Red'], AttributeOption::create('Red')->toArray());
    }

    public function testStoreLabelsBecomeStoreIdLabelPairs(): void
    {
        $option = AttributeOption::create('Red', sortOrder: 10, isDefault: true)
            ->withStoreLabel(1, 'Κόκκινο')
            ->withStoreLabels([2 => 'Rouge', 3 => 'Rot']);

        self::assertSame([
            'label'        => 'Red',
            'sort_order'   => 10,
            'is_default'   => true,
            'store_labels' => [
                ['store_id' => 1, 'label' => 'Κόκκινο'],
                ['store_id' => 2, 'label' => 'Rouge'],
                ['store_id' => 3, 'label' => 'Rot'],
            ],
        ], $option->toArray());
    }

    public function testWithStoreLabelIsImmutable(): void
    {
        $base    = AttributeOption::create('Red');
        $localised = $base->withStoreLabel(1, 'Κόκκινο');

        self::assertArrayNotHasKey('store_labels', $base->toArray());
        self::assertArrayHasKey('store_labels', $localised->toArray());
    }
}
