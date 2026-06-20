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
}
