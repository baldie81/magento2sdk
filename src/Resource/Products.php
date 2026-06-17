<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Resource;

use Baldie81\Magento2SDK\Enum\ProductStatus;
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
     * Create a product. Pass the product body without the "product" wrapper.
     *
     * @param array<string,mixed> $product
     * @return array<string,mixed>
     */
    public function create(array $product): array
    {
        return (array) $this->http->post('V1/products', ['product' => $product]);
    }

    /**
     * Create or update a product (Magento upserts by SKU on PUT).
     *
     * @param array<string,mixed> $product
     * @return array<string,mixed>
     */
    public function update(string $sku, array $product): array
    {
        return (array) $this->http->put('V1/products/' . $this->segment($sku), ['product' => $product]);
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
        return $this->paginateItems('V1/products', $criteria, $pageSize);
    }

    /**
     * Resolve many SKUs to their entity ids.
     *
     * The `sku in (...)` value and the result page are both capped on Magento
     * (long URIs 414; pageSize is bounded server-side), so the SKUs are chunked
     * and each chunk is paginated until a short page signals the end.
     *
     * @param list<string> $skus
     * @return array<string,int> sku => entity id
     */
    public function searchBySkus(array $skus, int $chunkSize = self::MAX_PAGE_SIZE): array
    {
        $map = [];

        foreach (array_chunk(array_values($skus), max(1, $chunkSize)) as $chunk) {
            $criteria = SearchCriteria::create()
                ->where('sku', $chunk, 'in')
                ->fields('items[id,sku]');

            foreach ($this->paginateItems('V1/products', $criteria, $chunkSize) as $item) {
                if (isset($item['sku'], $item['id'])) {
                    $map[(string) $item['sku']] = (int) $item['id'];
                }
            }
        }

        return $map;
    }
}
