<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Tests\Resource;

use GuzzleHttp\Psr7\Response;
use Baldie81\Magento2SDK\Tests\MockClientTestCase;

final class ProductMediaTest extends MockClientTestCase
{
    public function testCreateReturnsEntryIdAndWrapsEntry(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode(31))]);

        $id = $client->media()->create('SKU 1', [
            'media_type' => 'image',
            'label'      => 'Front',
            'types'      => ['image'],
        ]);

        self::assertSame(31, $id);
        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertStringContainsString('rest/V1/products/SKU%201/media', (string) $request->getUri());
        self::assertSame('image', json_decode((string) $request->getBody(), true)['entry']['media_type']);
    }

    public function testDeleteHitsEntryEndpoint(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode(true))]);

        self::assertTrue($client->media()->delete('A', 31));
        $request = $this->lastRequest();
        self::assertSame('DELETE', $request->getMethod());
        self::assertStringContainsString('rest/V1/products/A/media/31', (string) $request->getUri());
    }
}
