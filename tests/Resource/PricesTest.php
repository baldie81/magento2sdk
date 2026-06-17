<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Tests\Resource;

use GuzzleHttp\Psr7\Response;
use Baldie81\Magento2SDK\Support\Price;
use Baldie81\Magento2SDK\Tests\MockClientTestCase;

final class PricesTest extends MockClientTestCase
{
    public function testSetBasePricesAcceptsPriceObjects(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode([]))]);

        $client->prices()->setBasePrices([
            Price::make('A', 9.9),
            ['sku' => 'B', 'price' => 5.0, 'store_id' => 0], // raw array still works
        ]);

        $body = json_decode((string) $this->lastRequest()->getBody(), true);
        self::assertSame('A', $body['prices'][0]['sku']);
        self::assertSame(0, $body['prices'][0]['store_id']);
        self::assertSame('B', $body['prices'][1]['sku']);
    }

    public function testSetBasePriceWrapsSingleEntryAndReturnsFailures(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode([]))]);

        $failed = $client->prices()->setBasePrice('SKU-1', 19.9);

        self::assertSame([], $failed);
        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertStringContainsString('rest/V1/products/base-prices', (string) $request->getUri());
        $body = json_decode((string) $request->getBody(), true);
        self::assertSame('SKU-1', $body['prices'][0]['sku']);
        self::assertSame(19.9, $body['prices'][0]['price']);
        self::assertSame(0, $body['prices'][0]['store_id']);
    }

    public function testSetSpecialPricesPostsToSpecialPriceEndpoint(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode([]))]);

        $client->prices()->setSpecialPrices([
            ['sku' => 'A', 'store_id' => 0, 'price' => 9.99, 'price_from' => '2026-01-01 00:00:00'],
        ]);

        self::assertStringContainsString('rest/V1/products/special-price', (string) $this->lastRequest()->getUri());
    }
}
