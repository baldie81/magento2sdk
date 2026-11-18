<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Resource;

use Baldie81\Magento2SDK\Exception\ApiException;
use Baldie81\Magento2SDK\Http\HttpClient;
use Baldie81\Magento2SDK\Support\Arrayable;
use Baldie81\Magento2SDK\Support\SearchCriteria;

abstract class AbstractResource
{
    /**
     * Magento caps `searchCriteria[pageSize]` server-side (300 by default);
     * asking for more is silently truncated to this, which would break naive
     * "short page == last page" pagination, so requests are capped here.
     */
    protected const MAX_PAGE_SIZE = 300;

    /**
     * Paths whose default sort the store rejected, so later walks on this
     * resource skip it instead of failing, retrying and warning every time.
     *
     * @var array<string,true>
     */
    private array $rejectedDefaultSorts = [];

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
     * Normalise an object-or-array argument to the array payload Magento wants.
     *
     * @param Arrayable|array<string,mixed> $value
     * @return array<string,mixed>
     */
    protected function payload(Arrayable|array $value): array
    {
        return $value instanceof Arrayable ? $value->toArray() : $value;
    }

    /**
     * Normalise a list of object-or-array arguments.
     *
     * @param iterable<Arrayable|array<string,mixed>> $values
     * @return list<array<string,mixed>>
     */
    protected function payloads(iterable $values): array
    {
        $out = [];
        foreach ($values as $value) {
            $out[] = $this->payload($value);
        }

        return $out;
    }

    /**
     * Normalise the response of an endpoint that returns a bare JSON list of
     * entities: anything that isn't a list of objects yields no rows rather
     * than a bogus one.
     *
     * @return list<array<string,mixed>>
     */
    protected function rows(mixed $response): array
    {
        $rows = [];
        foreach (is_array($response) ? $response : [] as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Fetch every page of a list/search endpoint and return the merged `items`.
     *
     * Magento caps the page size regardless of what you request, so a single
     * call silently truncates large result sets. This walks `currentPage` —
     * capping the page size at {@see MAX_PAGE_SIZE} — until everything is
     * collected. When the response carries `total_count` that is authoritative
     * (so a store whose page-size cap is lower than requested still yields
     * everything); otherwise a short page ends the walk. Magento answers an
     * out-of-range `currentPage` with the last page again rather than an empty
     * one, so an empty or repeated page always stops the loop.
     *
     * `$defaultSort` is applied only when the criteria has no sort of its own,
     * to keep page boundaries stable while the data changes. Should the store
     * reject that sort, the walk is retried without it and an E_USER_WARNING
     * says so — the SDK's own default never makes a search fail. The passed
     * criteria is not mutated.
     *
     * @return list<array<string,mixed>>
     */
    protected function paginateItems(
        string $path,
        SearchCriteria $criteria,
        int $pageSize = self::MAX_PAGE_SIZE,
        ?string $defaultSort = null,
    ): array {
        $criteria = clone $criteria;
        $pageSize = max(1, min($pageSize, self::MAX_PAGE_SIZE));

        // Kept so the walk can fall back to the caller's criteria as given if
        // the store rejects the sort the SDK added.
        $unsorted = null;
        if ($defaultSort !== null && !$criteria->hasSort() && !isset($this->rejectedDefaultSorts[$path])) {
            $unsorted = clone $criteria;
            $criteria->sort($defaultSort);
        }

        $items    = [];
        $page     = 1;
        $previous = null;

        while (true) {
            $criteria->pageSize($pageSize)->currentPage($page);

            try {
                $response = (array) $this->http->get($path, $criteria->toArray());
            } catch (ApiException $e) {
                if ($unsorted === null || $page !== 1) {
                    throw $e;
                }

                // Retry once without the SDK's sort. If that fails too the
                // sort wasn't the problem and the error propagates as usual.
                $criteria = $unsorted->pageSize($pageSize)->currentPage($page);
                $unsorted = null;
                $response = (array) $this->http->get($path, $criteria->toArray());

                $this->rejectedDefaultSorts[$path] = true;
                trigger_error(sprintf(
                    'magento2-sdk: the default sort "%s" was rejected by %s (HTTP %d: %s). Results were fetched'
                    . ' without it, so pages may shift if data changes during the walk; pass an explicit'
                    . ' sort() on the SearchCriteria to choose the order yourself.',
                    $defaultSort,
                    $path,
                    $e->statusCode,
                    $e->getMessage(),
                ), E_USER_WARNING);
            }

            $batch = $response['items'] ?? [];
            if (!is_array($batch) || $batch === [] || $batch === $previous) {
                break;
            }
            foreach ($batch as $item) {
                $items[] = $item;
            }

            $total = isset($response['total_count']) ? (int) $response['total_count'] : null;
            if ($total !== null ? count($items) >= $total : count($batch) < $pageSize) {
                break;
            }

            $previous = $batch;
            $page++;
        }

        return $items;
    }
}
