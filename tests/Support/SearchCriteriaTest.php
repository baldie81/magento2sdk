<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Tests\Support;

use Baldie81\Magento2SDK\Support\SearchCriteria;
use PHPUnit\Framework\TestCase;

final class SearchCriteriaTest extends TestCase
{
    public function testBuildsFilterGroupsSortPagingAndFields(): void
    {
        $query = SearchCriteria::create()
            ->where('status', 1)
            ->where('price', 100, 'gt')
            ->whereAny([['sku', 'A'], ['sku', 'B', 'eq']])
            ->sort('created_at', 'desc')
            ->pageSize(50)
            ->currentPage(2)
            ->fields('items[sku]', 'total_count')
            ->toArray();

        // Each where() is its own AND group.
        self::assertSame('status', $query['searchCriteria[filterGroups][0][filters][0][field]']);
        self::assertSame('1', $query['searchCriteria[filterGroups][0][filters][0][value]']);
        self::assertSame('eq', $query['searchCriteria[filterGroups][0][filters][0][conditionType]']);
        self::assertSame('price', $query['searchCriteria[filterGroups][1][filters][0][field]']);
        self::assertSame('gt', $query['searchCriteria[filterGroups][1][filters][0][conditionType]']);

        // whereAny() is a single group with OR'd filters.
        self::assertSame('A', $query['searchCriteria[filterGroups][2][filters][0][value]']);
        self::assertSame('B', $query['searchCriteria[filterGroups][2][filters][1][value]']);

        self::assertSame('created_at', $query['searchCriteria[sortOrders][0][field]']);
        self::assertSame('DESC', $query['searchCriteria[sortOrders][0][direction]']);
        self::assertSame(50, $query['searchCriteria[pageSize]']);
        self::assertSame(2, $query['searchCriteria[currentPage]']);
        self::assertSame('items[sku],total_count', $query['fields']);
    }

    public function testArrayValueIsJoinedWithCommasForInCondition(): void
    {
        $query = SearchCriteria::create()
            ->where('sku', ['A', 'B', 'C'], 'in')
            ->toArray();

        self::assertSame('A,B,C', $query['searchCriteria[filterGroups][0][filters][0][value]']);
        self::assertSame('in', $query['searchCriteria[filterGroups][0][filters][0][conditionType]']);
    }
}
