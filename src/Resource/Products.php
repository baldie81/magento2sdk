<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Resource;

use Baldie81\Magento2SDK\Enum\ProductStatus;
use Baldie81\Magento2SDK\Support\Product;
use Baldie81\Magento2SDK\Support\SearchCriteria;

/**
 * Products endpoint (`rest/V1/products`).
 */
final class Products extends AbstractResource
{
    /**
     * Budget for the SKU part of a search URL. Web servers commonly refuse a
     * request line over 8 KB (HTTP 414); this leaves room for the base URL,
     * the paging/sort/fields parameters and any proxy in front of the store.
     */
    private const MAX_QUERY_BYTES = 4000;

    /**
     * @return array<string,mixed> the product entity
     */
    public function get(string $sku): array
    {
        return (array) $this->http->get('V1/products/' . $this->segment($sku));
    }

    /**
     * Create a product. Accepts a {@see Product} builder or a raw array (the
     * inner product body, without the "product" wrapper).
     *
     * @param Product|array<string,mixed> $product
     * @return array<string,mixed>
     */
    public function create(Product|array $product): array
    {
        return (array) $this->http->post('V1/products', ['product' => $this->payload($product)]);
    }

    /**
     * Create or update a product (Magento upserts by SKU on PUT).
     *
     * @param Product|array<string,mixed> $product
     * @return array<string,mixed>
     */
    public function update(string $sku, Product|array $product): array
    {
        return (array) $this->http->put('V1/products/' . $this->segment($sku), ['product' => $this->payload($product)]);
    }

    public function delete(string $sku): bool
    {
        return (bool) $this->http->delete('V1/products/' . $this->segment($sku));
    }

    /**
     * Set a product's status by SKU.
     *
     * @return array<string,mixed>
     */
    public function setStatus(string $sku, ProductStatus $status): array
    {
        return $this->update($sku, ['sku' => $sku, 'status' => $status->value]);
    }

    /**
     * @return array<string,mixed>
     */
    public function enable(string $sku): array
    {
        return $this->setStatus($sku, ProductStatus::Enabled);
    }

    /**
     * @return array<string,mixed>
     */
    public function disable(string $sku): array
    {
        return $this->setStatus($sku, ProductStatus::Disabled);
    }

    /**
     * Run a product search for a single page. Returns the full Magento envelope
     * (items, search_criteria, total_count). Use {@see searchAll()} to retrieve
     * every page.
     *
     * @return array<string,mixed>
     */
    public function search(SearchCriteria $criteria): array
    {
        return (array) $this->http->get('V1/products', $criteria->toArray());
    }

    /**
     * Like {@see search()} but paginates and returns every matching product
     * (merged `items`), working around Magento's page-size cap.
     *
     * @return list<array<string,mixed>>
     */
    public function searchAll(SearchCriteria $criteria, int $pageSize = self::MAX_PAGE_SIZE): array
    {
        return $this->paginateItems('V1/products', $criteria, $pageSize, 'entity_id');
    }

    /**
     * Resolve many SKUs to their entity ids.
     *
     * The `sku in (...)` value and the result page are both capped on Magento
     * (long URIs 414; pageSize is bounded server-side), so the SKUs are chunked
     * — by `$chunkSize` and by the encoded length of the URL they produce,
     * whichever is reached first — and each chunk is paginated until
     * everything is collected.
     *
     * The `in` set is comma-separated with no way to escape a comma, so SKUs
     * that contain one are looked up separately with OR'd `eq` filters.
     *
     * The result is keyed by the SKUs **as you passed them**: Magento matches
     * SKUs case-insensitively, so "abc-1" finds a product stored as "ABC-1"
     * and is returned under "abc-1". SKUs that don't exist are absent. Note
     * that PHP turns an all-digit array key such as "123" into the integer
     * 123 (a key like "00123" stays a string).
     *
     * @param list<string|int> $skus
     * @return array<string|int,int> sku => entity id
     */
    public function searchBySkus(array $skus, int $chunkSize = self::MAX_PAGE_SIZE): array
    {
        $chunkSize = max(1, $chunkSize);
        $plain     = [];
        $withComma = [];
        $requested = []; // lower-cased sku => the spellings the caller used
        foreach (array_unique(array_map('strval', $skus)) as $sku) {
            if ($sku === '') {
                continue;
            }
            $requested[mb_strtolower($sku, 'UTF-8')][] = $sku;

            if (str_contains($sku, ',')) {
                $withComma[] = $sku;
            } else {
                $plain[] = $sku;
            }
        }

        $searches = [];
        // One "%2C"-joined value: each SKU costs its encoded length + 3.
        foreach ($this->chunkForUrl($plain, $chunkSize, 3) as $chunk) {
            $searches[] = SearchCriteria::create()->where('sku', $chunk, 'in');
        }
        // A whole filter (field, value, conditionType) per SKU: ~260 bytes each.
        foreach ($this->chunkForUrl($withComma, $chunkSize, 260) as $chunk) {
            $searches[] = SearchCriteria::create()
                ->whereAny(array_map(static fn (string $sku) => ['sku', $sku], $chunk));
        }

        $map = [];
        foreach ($searches as $criteria) {
            $criteria->fields('items[id,sku]', 'total_count');

            foreach ($this->paginateItems('V1/products', $criteria, $chunkSize, 'entity_id') as $item) {
                if (!isset($item['sku'], $item['id'])) {
                    continue;
                }
                $found = (string) $item['sku'];

                foreach ($requested[mb_strtolower($found, 'UTF-8')] ?? [$found] as $sku) {
                    $map[$sku] = (int) $item['id'];
                }
            }
        }

        return $map;
    }

    /**
     * Split SKUs into chunks of at most `$maxCount` whose URL-encoded size
     * (plus `$overhead` bytes each) stays within {@see MAX_QUERY_BYTES}.
     *
     * @param list<string> $skus
     * @return list<list<string>>
     */
    private function chunkForUrl(array $skus, int $maxCount, int $overhead): array
    {
        $chunks = [];
        $chunk  = [];
        $bytes  = 0;

        foreach ($skus as $sku) {
            $cost = strlen(rawurlencode($sku)) + $overhead;

            if ($chunk !== [] && (count($chunk) >= $maxCount || $bytes + $cost > self::MAX_QUERY_BYTES)) {
                $chunks[] = $chunk;
                $chunk    = [];
                $bytes    = 0;
            }
            $chunk[] = $sku;
            $bytes  += $cost;
        }
        if ($chunk !== []) {
            $chunks[] = $chunk;
        }

        return $chunks;
    }
}
