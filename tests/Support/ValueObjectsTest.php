<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Tests\Support;

use Baldie81\Magento2SDK\Enum\ProductStatus;
use Baldie81\Magento2SDK\Enum\Visibility;
use Baldie81\Magento2SDK\Support\Arrayable;
use Baldie81\Magento2SDK\Support\Category;
use Baldie81\Magento2SDK\Support\MediaEntry;
use Baldie81\Magento2SDK\Support\Price;
use Baldie81\Magento2SDK\Support\Product;
use Baldie81\Magento2SDK\Support\StockItem;
use PHPUnit\Framework\TestCase;

final class ValueObjectsTest extends TestCase
{
    public function testProductBuildsNestedPayload(): void
    {
        $product = Product::make('SKU-1')
            ->name('Example')
            ->attributeSetId(4)
            ->type('simple')
            ->price(19.90)
            ->status(ProductStatus::Enabled)
            ->visibility(4)
            ->websiteIds([1, 3])
            ->customAttribute('color', 49)
            ->customAttributes(['url_key' => 'sku-1']);

        self::assertInstanceOf(Arrayable::class, $product);
        self::assertSame([
            'sku'                  => 'SKU-1',
            'name'                 => 'Example',
            'attribute_set_id'     => 4,
            'type_id'              => 'simple',
            'price'                => 19.90,
            'status'               => 1,
            'visibility'           => 4,
            'extension_attributes' => ['website_ids' => [1, 3]],
            'custom_attributes'    => [
                ['attribute_code' => 'color', 'value' => 49],
                ['attribute_code' => 'url_key', 'value' => 'sku-1'],
            ],
        ], $product->toArray());
    }

    public function testProductOmitsUnsetFields(): void
    {
        self::assertSame(['sku' => 'X'], Product::make('X')->toArray());
    }

    public function testProductVisibilityAcceptsEnumOrInt(): void
    {
        self::assertSame(4, Product::make('X')->visibility(Visibility::CatalogSearch)->toArray()['visibility']);
        self::assertSame(1, Product::make('X')->visibility(1)->toArray()['visibility']);
    }

    public function testProductCarriesMediaGalleryEntries(): void
    {
        $product = Product::make('X')->mediaGalleryEntry(
            MediaEntry::image('B64', 'image/jpeg', 'front.jpg')->label('Front'),
        );

        self::assertSame([[
            'media_type' => 'image',
            'content'    => ['base64_encoded_data' => 'B64', 'type' => 'image/jpeg', 'name' => 'front.jpg'],
            'label'      => 'Front',
        ]], $product->toArray()['media_gallery_entries']);
    }

    public function testConfigurableProductCarriesOptionsAndLinks(): void
    {
        $product = Product::make('PARENT')
            ->type('configurable')
            ->configurableOption(93, [49, 239], 'Color')
            ->configurableLinks([10, 11]);

        $ext = $product->toArray()['extension_attributes'];
        self::assertSame([[
            'attribute_id' => '93',
            'label'        => 'Color',
            'values'       => [['value_index' => 49], ['value_index' => 239]],
        ]], $ext['configurable_product_options']);
        self::assertSame([10, 11], $ext['configurable_product_links']);
    }

    public function testStockItemOfQuantityDerivesInStock(): void
    {
        self::assertSame(['qty' => 5.0, 'is_in_stock' => true], StockItem::ofQuantity(5.0)->toArray());
        self::assertSame(['qty' => 0.0, 'is_in_stock' => false], StockItem::ofQuantity(0)->toArray());
    }

    public function testPriceBaseAndSpecial(): void
    {
        self::assertSame(
            ['sku' => 'A', 'price' => 9.9, 'store_id' => 0],
            Price::make('A', 9.9)->toArray(),
        );
        self::assertSame(
            ['sku' => 'A', 'price' => 5.0, 'store_id' => 1, 'price_from' => '2026-06-01 00:00:00'],
            Price::make('A', 5.0, 1)->from('2026-06-01 00:00:00')->toArray(),
        );
    }

    public function testCategoryWithCustomAttribute(): void
    {
        self::assertSame([
            'name'              => 'Shoes',
            'parent_id'         => 2,
            'is_active'         => true,
            'custom_attributes' => [['attribute_code' => 'description', 'value' => 'Nice']],
        ], Category::make('Shoes', 2)->isActive(true)->customAttribute('description', 'Nice')->toArray());
    }

    public function testMediaEntryImage(): void
    {
        $entry = MediaEntry::image('BASE64', 'image/jpeg', 'front.jpg')
            ->label('Front')
            ->roles(['image', 'thumbnail']);

        self::assertSame([
            'media_type' => 'image',
            'content'    => ['base64_encoded_data' => 'BASE64', 'type' => 'image/jpeg', 'name' => 'front.jpg'],
            'label'      => 'Front',
            'types'      => ['image', 'thumbnail'],
        ], $entry->toArray());
    }
}
