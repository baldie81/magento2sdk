<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Tests\Resource;

use GuzzleHttp\Psr7\Response;
use Baldie81\Magento2SDK\Tests\MockClientTestCase;

final class StockTest extends MockClientTestCase
{
    public function testSetQuantityPutsStockItemAndDerivesInStock(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode(42))]);

        $itemId = $client->stock()->setQuantity('SKU 1', 5.0);

        self::assertSame(42, $itemId);
        $request = $this->lastRequest();
        self::assertSame('PUT', $request->getMethod());
        self::assertStringContainsString('rest/V1/products/SKU%201/stockItems/0', (string) $request->getUri());
        $body = json_decode((string) $request->getBody(), true);
        self::assertEquals(5.0, $body['stockItem']['qty']);
        self::assertTrue($body['stockItem']['is_in_stock']);
    }

    public function testSetQuantityZeroMarksOutOfStock(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode(1))]);

        $client->stock()->setQuantity('A', 0);

        $body = json_decode((string) $this->lastRequest()->getBody(), true);
        self::assertFalse($body['stockItem']['is_in_stock']);
    }

    public function testSaveSourceItemsPostsWrappedList(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode([]))]);

        $client->stock()->saveSourceItems([
            ['sku' => 'A', 'source_code' => 'default', 'quantity' => 3, 'status' => 1],
        ]);

        $request = $this->lastRequest();
        self::assertStringContainsString('rest/V1/inventory/source-items', (string) $request->getUri());
        $body = json_decode((string) $request->getBody(), true);
        self::assertSame('A', $body['sourceItems'][0]['sku']);
    }
}
