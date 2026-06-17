<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Resource;

use Baldie81\Magento2SDK\Http\HttpClient;
use Baldie81\Magento2SDK\Support\SearchCriteria;

abstract class AbstractResource
{
    /**
     * Magento caps `searchCriteria[pageSize]` server-side (300 by default);
     * asking for more is silently truncated to this, which would break naive
     * "short page == last page" pagination, so requests are capped here.
     */
    protected const MAX_PAGE_SIZE = 300;

    public function __construct(
        protected readonly HttpClient $http,
    ) {}

    /**
     * URL-encode a single path segment (SKUs and attribute codes may contain
     * spaces, slashes, etc.).
     */
    protected function segment(string $value): string
    {
        return rawurlencode($value);
    }

    /**
     * Fetch every page of a list/search endpoint and return the merged `items`.
     *
     * Magento caps the page size regardless of what you request, so a single
     * call silently truncates large result sets. This walks `currentPage` —
     * capping the page size at {@see MAX_PAGE_SIZE} — until everything is
     * collected, using `total_count` when the response carries it and falling
     * back to a short page otherwise. The passed criteria is not mutated.
     *
     * @return list<array<string,mixed>>
     */
    protected function paginateItems(
        string $path,
        SearchCriteria $criteria,
        int $pageSize = self::MAX_PAGE_SIZE,
    ): array {
        $criteria = clone $criteria;
        $pageSize = max(1, min($pageSize, self::MAX_PAGE_SIZE));

        $items = [];
        $page  = 1;

        do {
            $criteria->pageSize($pageSize)->currentPage($page);
            $response = (array) $this->http->get($path, $criteria->toArray());

            $batch = $response['items'] ?? [];
            if (!is_array($batch)) {
                break;
            }
            foreach ($batch as $item) {
                $items[] = $item;
            }

            $total = isset($response['total_count']) ? (int) $response['total_count'] : null;
            $page++;
        } while (count($batch) === $pageSize && ($total === null || count($items) < $total));

        return $items;
    }
}
