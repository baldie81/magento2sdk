<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Tests\Resource;

use GuzzleHttp\Psr7\Response;
use Baldie81\Magento2SDK\Tests\MockClientTestCase;

final class AttributeSetsTest extends MockClientTestCase
{
    public function testAllListsAttributeSets(): void
    {
        $client = $this->mockClient([new Response(200, [], (string) json_encode([
            'items' => [
                ['attribute_set_id' => 4, 'attribute_set_name' => 'Default'],
                ['attribute_set_id' => 9, 'attribute_set_name' => 'Apparel'],
            ],
            'total_count' => 2,
        ]))]);

        $sets = $client->attributeSets()->all();

        self::assertCount(2, $sets);
        self::assertSame('Apparel', $sets[1]['attribute_set_name']);
        self::assertStringContainsString('V1/eav/attribute-sets/list', (string) $this->lastRequest()->getUri());
    }

    public function testAllWalksEveryPage(): void
    {
        $client = $this->mockClient([
            new Response(200, [], (string) json_encode([
                'items'       => array_fill(0, 300, ['attribute_set_id' => 1]),
                'total_count' => 301,
            ])),
            new Response(200, [], (string) json_encode([
                'items'       => [['attribute_set_id' => 2]],
                'total_count' => 301,
            ])),
        ]);

        self::assertCount(301, $client->attributeSets()->all());
        self::assertCount(2, $this->history);
    }

    public function testGroupsListsGroupsForASet(): void
    {
        $client = $this->mockClient([new Response(200, [], (string) json_encode([
            'items' => [
                ['attribute_group_id' => 7, 'attribute_group_name' => 'General', 'attribute_set_id' => 9],
            ],
            'total_count' => 1,
        ]))]);

        $groups = $client->attributeSets()->groups(9);

        self::assertSame('General', $groups[0]['attribute_group_name']);
    }

    public function testCreateCreatesAttributeSet(): void
    {
        $client = $this->mockClient([new Response(200, [], (string) json_encode([
            'attribute_set_id' => 11, 'attribute_set_name' => 'Footwear', 'entity_type_id' => 4,
        ]))]);

        $set = $client->attributeSets()->create('Footwear');

        self::assertSame(11, $set['attribute_set_id']);
        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertStringContainsString('V1/products/attribute-sets', (string) $request->getUri());
        $body = json_decode((string) $request->getBody(), true);
        self::assertSame('Footwear', $body['attributeSet']['attribute_set_name']);
        self::assertSame(4, $body['skeletonId']);
    }

    public function testDeleteDeletesAttributeSet(): void
    {
        $client = $this->mockClient([new Response(200, [], 'true')]);

        self::assertTrue($client->attributeSets()->delete(11));
        self::assertSame('DELETE', $this->lastRequest()->getMethod());
        self::assertStringContainsString('V1/products/attribute-sets/11', (string) $this->lastRequest()->getUri());
    }

    public function testCreateGroupCreatesGroup(): void
    {
        $client = $this->mockClient([new Response(200, [], (string) json_encode([
            'attribute_group_id' => 12, 'attribute_group_name' => 'Soles', 'attribute_set_id' => 11,
        ]))]);

        $group = $client->attributeSets()->createGroup(11, 'Soles');

        self::assertSame(12, $group['attribute_group_id']);
        $body = json_decode((string) $this->lastRequest()->getBody(), true);
        self::assertSame('POST', $this->lastRequest()->getMethod());
        self::assertStringContainsString('V1/products/attribute-sets/groups', (string) $this->lastRequest()->getUri());
        self::assertSame('Soles', $body['group']['attribute_group_name']);
        self::assertSame(11, $body['group']['attribute_set_id']);
    }

    public function testDeleteGroupDeletesGroup(): void
    {
        $client = $this->mockClient([new Response(200, [], 'true')]);

        self::assertTrue($client->attributeSets()->deleteGroup(12));
        self::assertSame('DELETE', $this->lastRequest()->getMethod());
        self::assertStringContainsString('V1/products/attribute-sets/groups/12', (string) $this->lastRequest()->getUri());
    }

    public function testSetGroupSortOrderUsesExtensionAttributes(): void
    {
        $client = $this->mockClient([new Response(200, [], (string) json_encode([
            'attribute_group_id' => 7, 'attribute_group_name' => 'General', 'attribute_set_id' => 9,
        ]))]);

        $client->attributeSets()->setGroupSortOrder(7, 9, 'General', 3);

        $body = json_decode((string) $this->lastRequest()->getBody(), true);
        self::assertSame('POST', $this->lastRequest()->getMethod());
        self::assertStringContainsString('V1/products/attribute-sets/groups', (string) $this->lastRequest()->getUri());
        self::assertSame(7, $body['group']['attribute_group_id']);
        self::assertSame('General', $body['group']['attribute_group_name']);
        self::assertSame(3, $body['group']['extension_attributes']['sort_order']);
    }
}
