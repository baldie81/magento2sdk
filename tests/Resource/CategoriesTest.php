<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Tests\Resource;

use GuzzleHttp\Psr7\Response;
use Baldie81\Magento2SDK\Tests\MockClientTestCase;

final class CategoriesTest extends MockClientTestCase
{
    public function testListFlatPaginatesEveryPage(): void
    {
        $client = $this->mockClient([
            new Response(200, [], json_encode(['items' => array_fill(0, 300, ['id' => 1]), 'total_count' => 320])),
            new Response(200, [], json_encode(['items' => array_fill(0, 20, ['id' => 2]), 'total_count' => 320])),
        ]);

        $items = $client->categories()->listFlat();

        self::assertCount(320, $items);
        self::assertCount(2, $this->history);
        self::assertStringContainsString('rest/V1/categories/list', (string) $this->history[0]['request']->getUri());
    }

    public function testListFlatStopsOnShortPageWhenTotalCountAbsent(): void
    {
        $client = $this->mockClient([
            new Response(200, [], json_encode(['items' => array_fill(0, 300, ['id' => 1])])),
            new Response(200, [], json_encode(['items' => array_fill(0, 5, ['id' => 2])])),
        ]);

        $items = $client->categories()->listFlat();

        self::assertCount(305, $items);
        self::assertCount(2, $this->history);
    }

    public function testCreateWrapsCategory(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode(['id' => 12, 'name' => 'Shoes']))]);

        $category = $client->categories()->create(['name' => 'Shoes', 'parent_id' => 2]);

        self::assertSame(12, $category['id']);
        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('Shoes', json_decode((string) $request->getBody(), true)['category']['name']);
    }

    public function testAssignProductPostsProductLinkWithStringCategoryId(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode(true))]);

        $ok = $client->categories()->assignProduct(12, 'SKU-1', 3);

        self::assertTrue($ok);
        $request = $this->lastRequest();
        self::assertStringContainsString('rest/V1/categories/12/products', (string) $request->getUri());
        $link = json_decode((string) $request->getBody(), true)['productLink'];
        self::assertSame(['sku' => 'SKU-1', 'category_id' => '12', 'position' => 3], $link);
    }

    public function testRemoveProductDeletesEncodedSku(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode(true))]);

        $client->categories()->removeProduct(12, 'SKU 1');

        $request = $this->lastRequest();
        self::assertSame('DELETE', $request->getMethod());
        self::assertStringContainsString('rest/V1/categories/12/products/SKU%201', (string) $request->getUri());
    }
}
