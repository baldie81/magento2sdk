<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Tests\Resource;

use GuzzleHttp\Psr7\Response;
use Baldie81\Magento2SDK\Support\Product;
use Baldie81\Magento2SDK\Tests\MockClientTestCase;

final class BulkTest extends MockClientTestCase
{
    public function testCreateProductsWrapsProductObjects(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode(['bulk_uuid' => 'u']))]);

        $client->bulk()->createProducts([
            Product::make('A')->type('simple'),
            ['product' => ['sku' => 'B']], // raw, already-wrapped — passes through
        ]);

        $body = json_decode((string) $this->lastRequest()->getBody(), true);
        self::assertSame('A', $body[0]['product']['sku']);
        self::assertSame('simple', $body[0]['product']['type_id']);
        self::assertSame('B', $body[1]['product']['sku']);
    }

    public function testDispatchCollectsUuidsAndPerChunkErrors(): void
    {
        $client = $this->mockClient([
            new Response(200, [], json_encode(['bulk_uuid' => 'uuid-1'])),
            new Response(400, [], json_encode(['message' => 'Invalid %1', 'parameters' => ['the SKU']])),
            new Response(200, [], json_encode(['bulk_uuid' => 'uuid-3'])),
        ]);

        $chunks = [
            [['product' => ['sku' => 'A']]],
            [['product' => ['sku' => 'B']]],
            [['product' => ['sku' => 'C']]],
        ];

        $results = $client->bulk()->dispatchProducts($chunks, 2);

        self::assertSame('uuid-1', $results[0]['bulk_uuid']);
        self::assertArrayHasKey('error', $results[1]);
        self::assertStringContainsString('Invalid the SKU', $results[1]['error']);
        self::assertSame('uuid-3', $results[2]['bulk_uuid']);
    }

    public function testCreateProductsSendsJsonArrayBody(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode(['bulk_uuid' => 'uuid-x']))]);

        $response = $client->bulk()->createProducts([
            ['product' => ['sku' => 'A']],
            ['product' => ['sku' => 'B']],
        ]);

        self::assertSame('uuid-x', $response['bulk_uuid']);
        $body = json_decode((string) $this->lastRequest()->getBody(), true);
        self::assertCount(2, $body);
        self::assertSame('A', $body[0]['product']['sku']);
    }
}
