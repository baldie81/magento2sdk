<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Tests\Resource;

use GuzzleHttp\Psr7\Response;
use Baldie81\Magento2SDK\Enum\ProductStatus;
use Baldie81\Magento2SDK\Support\SearchCriteria;
use Baldie81\Magento2SDK\Tests\MockClientTestCase;

final class ProductsTest extends MockClientTestCase
{
    public function testGetEncodesSkuAndReturnsEntity(): void
    {
        $client  = $this->mockClient([new Response(200, [], json_encode(['sku' => 'A B', 'id' => 5]))]);
        $product = $client->products()->get('A B');

        self::assertSame(5, $product['id']);
        self::assertSame(
            'https://example.test/rest/V1/products/A%20B',
            (string) $this->lastRequest()->getUri(),
        );
    }

    public function testSearchBySkusPaginatesUntilShortPage(): void
    {
        $client = $this->mockClient([
            // page 1: exactly pageSize (2) items -> the client asks for another page
            new Response(200, [], json_encode(['items' => [
                ['sku' => 'S1', 'id' => 11],
                ['sku' => 'S2', 'id' => 12],
            ]])),
            // page 2: short page -> stop
            new Response(200, [], json_encode(['items' => []])),
        ]);

        $map = $client->products()->searchBySkus(['S1', 'S2'], 2);

        self::assertSame(['S1' => 11, 'S2' => 12], $map);
        self::assertCount(2, $this->history);
        parse_str((string) $this->history[1]['request']->getUri()->getQuery(), $secondPageQuery);
        self::assertSame('2', $secondPageQuery['searchCriteria']['currentPage']);
    }

    public function testSearchAllPaginatesUsingTotalCountAndMergesItems(): void
    {
        $client = $this->mockClient([
            new Response(200, [], json_encode(['items' => array_fill(0, 300, ['sku' => 'a']), 'total_count' => 450])),
            new Response(200, [], json_encode(['items' => array_fill(0, 150, ['sku' => 'b']), 'total_count' => 450])),
        ]);

        $items = $client->products()->searchAll(SearchCriteria::create()->where('status', 1));

        self::assertCount(450, $items);
        self::assertCount(2, $this->history);

        parse_str((string) $this->history[0]['request']->getUri()->getQuery(), $page1);
        self::assertSame('300', $page1['searchCriteria']['pageSize']);
        self::assertSame('1', $page1['searchCriteria']['currentPage']);

        parse_str((string) $this->history[1]['request']->getUri()->getQuery(), $page2);
        self::assertSame('2', $page2['searchCriteria']['currentPage']);
    }

    public function testSearchAllCapsRequestedPageSizeAtServerMax(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode(['items' => [], 'total_count' => 0]))]);

        $client->products()->searchAll(SearchCriteria::create(), 5000);

        parse_str((string) $this->history[0]['request']->getUri()->getQuery(), $query);
        self::assertSame('300', $query['searchCriteria']['pageSize']);
        self::assertCount(1, $this->history);
    }

    public function testSearchAllLeavesCallerCriteriaUntouched(): void
    {
        $client   = $this->mockClient([new Response(200, [], json_encode(['items' => [], 'total_count' => 0]))]);
        $criteria = SearchCriteria::create()->where('status', 1);

        $client->products()->searchAll($criteria);

        self::assertArrayNotHasKey('searchCriteria[pageSize]', $criteria->toArray());
        self::assertArrayNotHasKey('searchCriteria[currentPage]', $criteria->toArray());
    }

    public function testSetStatusSendsWrappedPutWithEnumValue(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode(['sku' => 'X', 'status' => 2]))]);
        $client->products()->setStatus('X', ProductStatus::Disabled);

        $request = $this->lastRequest();
        self::assertSame('PUT', $request->getMethod());
        $body = json_decode((string) $request->getBody(), true);
        self::assertSame(2, $body['product']['status']);
    }

    public function testEnableSendsStatusOne(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode(['sku' => 'X', 'status' => 1]))]);
        $client->products()->enable('X');

        $body = json_decode((string) $this->lastRequest()->getBody(), true);
        self::assertSame(1, $body['product']['status']);
    }
}
