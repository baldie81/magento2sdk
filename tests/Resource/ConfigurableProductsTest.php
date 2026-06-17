<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Tests\Resource;

use GuzzleHttp\Psr7\Response;
use Baldie81\Magento2SDK\Tests\MockClientTestCase;

final class ConfigurableProductsTest extends MockClientTestCase
{
    public function testAddChildPostsChildSku(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode(true))]);

        $ok = $client->configurables()->addChild('PARENT', 'CHILD-1');

        self::assertTrue($ok);
        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertStringContainsString('rest/V1/configurable-products/PARENT/child', (string) $request->getUri());
        self::assertSame(['childSku' => 'CHILD-1'], json_decode((string) $request->getBody(), true));
    }

    public function testRemoveChildDeletesEncodedChildSku(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode(true))]);

        $client->configurables()->removeChild('PARENT', 'CHILD 1');

        $request = $this->lastRequest();
        self::assertSame('DELETE', $request->getMethod());
        self::assertStringContainsString(
            'rest/V1/configurable-products/PARENT/children/CHILD%201',
            (string) $request->getUri(),
        );
    }

    public function testAddOptionReturnsOptionId(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode(7))]);

        $id = $client->configurables()->addOption('PARENT', [
            'attribute_id' => '93',
            'label'        => 'Size',
            'values'       => [['value_index' => 167], ['value_index' => 168]],
        ]);

        self::assertSame(7, $id);
        self::assertStringContainsString(
            'rest/V1/configurable-products/PARENT/options',
            (string) $this->lastRequest()->getUri(),
        );
    }
}
