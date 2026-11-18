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
     * and each chunk is paginated until everything is collected.
     *
     * The `in` set is comma-separated with no way to escape a comma, so SKUs
     * that contain one are looked up separately with OR'd `eq` filters.
     *
     * @param list<string> $skus
     * @return array<string,int> sku => entity id
     */
    public function searchBySkus(array $skus, int $chunkSize = self::MAX_PAGE_SIZE): array
    {
        $chunkSize = max(1, $chunkSize);
        $plain     = [];
        $withComma = [];
        foreach (array_unique(array_map('strval', $skus)) as $sku) {
            if (str_contains($sku, ',')) {
                $withComma[] = $sku;
            } else {
                $plain[] = $sku;
            }
        }

        $searches = [];
        foreach (array_chunk($plain, $chunkSize) as $chunk) {
            $searches[] = SearchCriteria::create()->where('sku', $chunk, 'in');
        }
        // Three query parameters per filter here, so keep these groups small.
        foreach (array_chunk($withComma, min($chunkSize, 50)) as $chunk) {
            $searches[] = SearchCriteria::create()
                ->whereAny(array_map(static fn (string $sku) => ['sku', $sku], $chunk));
        }

        $map = [];
        foreach ($searches as $criteria) {
            $criteria->fields('items[id,sku]', 'total_count');

            foreach ($this->paginateItems('V1/products', $criteria, $chunkSize, 'entity_id') as $item) {
                if (isset($item['sku'], $item['id'])) {
                    $map[(string) $item['sku']] = (int) $item['id'];
                }
            }
        }

        return $map;
    }
}
