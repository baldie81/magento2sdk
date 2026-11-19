<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Tests\Resource;

use GuzzleHttp\Psr7\Response;
use Baldie81\Magento2SDK\Enum\ProductStatus;
use Baldie81\Magento2SDK\Exception\ApiException;
use Baldie81\Magento2SDK\Support\Product;
use Baldie81\Magento2SDK\Support\SearchCriteria;
use Baldie81\Magento2SDK\Tests\MockClientTestCase;

final class ProductsTest extends MockClientTestCase
{
    public function testCreateAcceptsProductObject(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode(['sku' => 'SKU-1']))]);

        $client->products()->create(
            Product::make('SKU-1')->name('Example')->type('simple')->price(9.99),
        );

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        $body = json_decode((string) $request->getBody(), true);
        self::assertSame('SKU-1', $body['product']['sku']);
        self::assertSame('simple', $body['product']['type_id']);
    }

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
            // page 2: empty page -> stop
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

    public function testSearchBySkusStopsAfterOnePageWhenTotalCountIsReached(): void
    {
        $client = $this->mockClient([
            new Response(200, [], json_encode([
                'items'       => [['sku' => 'S1', 'id' => 11], ['sku' => 'S2', 'id' => 12]],
                'total_count' => 2,
            ])),
        ]);

        $map = $client->products()->searchBySkus(['S1', 'S2'], 2);

        self::assertSame(['S1' => 11, 'S2' => 12], $map);
        self::assertCount(1, $this->history);
        parse_str((string) $this->lastRequest()->getUri()->getQuery(), $query);
        self::assertSame('items[id,sku],total_count', $query['fields']);
    }

    public function testSearchBySkusLooksUpSkusContainingACommaWithEqFilters(): void
    {
        $client = $this->mockClient([
            new Response(200, [], json_encode(['items' => [['sku' => 'S1', 'id' => 11]], 'total_count' => 1])),
            new Response(200, [], json_encode(['items' => [['sku' => 'A,B', 'id' => 12]], 'total_count' => 1])),
        ]);

        $map = $client->products()->searchBySkus(['S1', 'A,B']);

        self::assertSame(['S1' => 11, 'A,B' => 12], $map);
        parse_str((string) $this->history[0]['request']->getUri()->getQuery(), $first);
        self::assertSame('S1', $first['searchCriteria']['filterGroups'][0]['filters'][0]['value']);
        self::assertSame('in', $first['searchCriteria']['filterGroups'][0]['filters'][0]['conditionType']);
        parse_str((string) $this->history[1]['request']->getUri()->getQuery(), $second);
        self::assertSame('A,B', $second['searchCriteria']['filterGroups'][0]['filters'][0]['value']);
        self::assertSame('eq', $second['searchCriteria']['filterGroups'][0]['filters'][0]['conditionType']);
    }

    public function testSearchAllStopsWhenMagentoRepeatsTheLastPage(): void
    {
        // No total_count, and Magento answers an out-of-range page with the
        // last page again instead of an empty one.
        $page   = ['items' => [['sku' => 'a'], ['sku' => 'b']]];
        $client = $this->mockClient([
            new Response(200, [], json_encode($page)),
            new Response(200, [], json_encode($page)),
        ]);

        $items = $client->products()->searchAll(SearchCriteria::create()->fields('items[sku]'), 2);

        self::assertCount(2, $items);
        self::assertCount(2, $this->history);
    }

    public function testSearchAllKeepsGoingPastAShortPageWhileTotalCountSaysThereIsMore(): void
    {
        // A store whose page-size cap is below what was requested.
        $client = $this->mockClient([
            new Response(200, [], json_encode(['items' => array_fill(0, 100, ['sku' => 'a']), 'total_count' => 150])),
            new Response(200, [], json_encode(['items' => array_fill(0, 50, ['sku' => 'b']), 'total_count' => 150])),
        ]);

        self::assertCount(150, $client->products()->searchAll(SearchCriteria::create()));
        self::assertCount(2, $this->history);
    }

    public function testSearchAllAddsAStableSortOnlyWhenTheCallerSetNone(): void
    {
        $empty  = json_encode(['items' => [], 'total_count' => 0]);
        $client = $this->mockClient([new Response(200, [], $empty), new Response(200, [], $empty)]);

        $client->products()->searchAll(SearchCriteria::create());
        parse_str((string) $this->lastRequest()->getUri()->getQuery(), $query);
        self::assertSame('entity_id', $query['searchCriteria']['sortOrders'][0]['field']);

        $client->products()->searchAll(SearchCriteria::create()->sort('sku', 'DESC'));
        parse_str((string) $this->lastRequest()->getUri()->getQuery(), $query);
        self::assertCount(1, $query['searchCriteria']['sortOrders']);
        self::assertSame('sku', $query['searchCriteria']['sortOrders'][0]['field']);
    }

    public function testSearchAllRetriesWithoutTheDefaultSortWhenItIsRejected(): void
    {
        $ok     = json_encode(['items' => [['sku' => 'a']], 'total_count' => 1]);
        $client = $this->mockClient([
            new Response(400, [], json_encode(['message' => 'Invalid attribute name: entity_id'])),
            new Response(200, [], $ok),
            new Response(200, [], $ok), // second walk: no sort, no retry
        ]);

        // Frameworks turn any PHP warning into an exception, so the fallback
        // must stay silent for the search to actually succeed.
        set_error_handler(static function (int $level, string $message): bool {
            throw new \ErrorException($message, 0, $level);
        });

        try {
            $first  = $client->products()->searchAll(SearchCriteria::create());
            $second = $client->products()->searchAll(SearchCriteria::create());
        } finally {
            restore_error_handler();
        }

        self::assertSame([['sku' => 'a']], $first);
        self::assertSame([['sku' => 'a']], $second);
        self::assertCount(3, $this->history);
        parse_str((string) $this->history[1]['request']->getUri()->getQuery(), $retry);
        self::assertArrayNotHasKey('sortOrders', $retry['searchCriteria']);
        parse_str((string) $this->history[2]['request']->getUri()->getQuery(), $later);
        self::assertArrayNotHasKey('sortOrders', $later['searchCriteria']);
    }

    public function testSearchAllDoesNotBlameATransientServerErrorOnTheDefaultSort(): void
    {
        $client = $this->mockClient([
            new Response(503, [], 'Service Unavailable'),
            new Response(200, [], json_encode(['items' => [['sku' => 'a']], 'total_count' => 1])),
        ]);

        try {
            $client->products()->searchAll(SearchCriteria::create());
            self::fail('Expected ApiException was not thrown.');
        } catch (ApiException $e) {
            self::assertSame(503, $e->statusCode);
            self::assertCount(1, $this->history); // not retried
        }

        // …and the default sort is still applied on the next walk.
        $client->products()->searchAll(SearchCriteria::create());
        parse_str((string) $this->lastRequest()->getUri()->getQuery(), $query);
        self::assertSame('entity_id', $query['searchCriteria']['sortOrders'][0]['field']);
    }

    public function testSearchBySkusKeepsEveryRequestUrlUnderTheServerLimit(): void
    {
        $empty  = json_encode(['items' => [], 'total_count' => 0]);
        $client = $this->mockClient(array_fill(0, 40, new Response(200, [], $empty)));

        $skus = [];
        for ($i = 0; $i < 300; $i++) {
            $skus[] = str_pad((string) $i, 64, 'X', STR_PAD_LEFT);
        }
        for ($i = 0; $i < 40; $i++) {
            $skus[] = 'WITH,COMMA-' . str_pad((string) $i, 40, 'Y', STR_PAD_LEFT);
        }

        $client->products()->searchBySkus($skus);

        self::assertGreaterThan(2, count($this->history));
        $seen = 0;
        foreach ($this->history as $entry) {
            self::assertLessThan(6000, strlen((string) $entry['request']->getUri()));
            parse_str((string) $entry['request']->getUri()->getQuery(), $query);
            foreach ($query['searchCriteria']['filterGroups'][0]['filters'] as $filter) {
                $seen += $filter['conditionType'] === 'in' ? count(explode(',', $filter['value'])) : 1;
            }
        }
        self::assertSame(340, $seen); // every SKU was asked for exactly once
    }

    public function testSearchBySkusKeysTheResultByTheSkusAsRequested(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode([
            'items'       => [['id' => 7, 'sku' => 'Abc-1'], ['id' => 8, 'sku' => 'XYZ']],
            'total_count' => 2,
        ]))]);

        $map = $client->products()->searchBySkus(['ABC-1', 'xyz', 'missing']);

        self::assertSame(['ABC-1' => 7, 'xyz' => 8], $map);
    }

    public function testIdentifiersThatWouldEscapeTheirRouteAreRefused(): void
    {
        $client = $this->mockClient([]);

        foreach (['', '.', '..'] as $sku) {
            try {
                $client->products()->delete($sku);
                self::fail('Expected InvalidArgumentException was not thrown.');
            } catch (\InvalidArgumentException) {
                self::assertCount(0, $this->history);
            }
        }
    }

    public function testSearchAllStillThrowsWhenTheFailureIsNotTheDefaultSort(): void
    {
        $client = $this->mockClient([
            new Response(401, [], json_encode(['message' => 'The consumer isn\'t authorized.'])),
            new Response(401, [], json_encode(['message' => 'The consumer isn\'t authorized.'])),
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage("The consumer isn't authorized.");
        $client->products()->searchAll(SearchCriteria::create());
    }

    public function testSearchAllDoesNotRetryWhenTheCallerChoseTheSort(): void
    {
        $client = $this->mockClient([
            new Response(400, [], json_encode(['message' => 'Invalid attribute name: nope'])),
        ]);

        try {
            $client->products()->searchAll(SearchCriteria::create()->sort('nope'));
            self::fail('Expected ApiException was not thrown.');
        } catch (ApiException $e) {
            self::assertCount(1, $this->history);
        }
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
