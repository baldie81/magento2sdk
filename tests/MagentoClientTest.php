<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Tests;

use GuzzleHttp\Psr7\Response;

final class MagentoClientTest extends MockClientTestCase
{
    public function testForStoreScopesRequestsToThatStoreView(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode(['sku' => 'X']))]);

        $client->forStore('fr')->products()->get('X');

        self::assertSame(
            'https://example.test/rest/fr/V1/products/X',
            (string) $this->lastRequest()->getUri(),
        );
    }

    public function testForStoreLeavesTheOriginalClientUnscoped(): void
    {
        $client = $this->mockClient([
            new Response(200, [], json_encode(['sku' => 'X'])),
            new Response(200, [], json_encode(['sku' => 'X'])),
        ]);

        $client->forStore('fr')->products()->get('X');
        $client->products()->get('X'); // original still default-scoped

        self::assertStringContainsString('/rest/fr/V1/products/X', (string) $this->history[0]['request']->getUri());
        self::assertStringContainsString('/rest/V1/products/X', (string) $this->history[1]['request']->getUri());
    }

    public function testForStoreWithEmptyCodeFallsBackToDefaultScope(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode(['sku' => 'X']))]);

        $client->forStore('')->products()->get('X');

        self::assertStringContainsString('/rest/V1/products/X', (string) $this->lastRequest()->getUri());
    }
}
