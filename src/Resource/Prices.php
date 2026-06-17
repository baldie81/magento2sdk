<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Resource;

/**
 * Base and special (sale) prices (`rest/V1/products/base-prices`,
 * `rest/V1/products/special-price`).
 *
 * Magento's price endpoints take a list and return the items that **failed**
 * (an empty array means everything succeeded).
 */
final class Prices extends AbstractResource
{
    /**
     * @param list<array<string,mixed>> $prices each ['sku' => …, 'price' => …, 'store_id' => 0]
     * @return array<int,mixed> failed items (empty = all saved)
     */
    public function setBasePrices(array $prices): array
    {
        return (array) $this->http->post('V1/products/base-prices', ['prices' => array_values($prices)]);
    }

    /**
     * Convenience: set one SKU's base price (store 0 = default/all).
     *
     * @return array<int,mixed> failed items (empty = saved)
     */
    public function setBasePrice(string $sku, float $price, int $storeId = 0): array
    {
        return $this->setBasePrices([['sku' => $sku, 'price' => $price, 'store_id' => $storeId]]);
    }

    /**
     * @param list<array<string,mixed>> $prices each ['sku','store_id','price','price_from','price_to']
     * @return array<int,mixed> failed items (empty = all saved)
     */
    public function setSpecialPrices(array $prices): array
    {
        return (array) $this->http->post('V1/products/special-price', ['prices' => array_values($prices)]);
    }

    /**
     * @param list<array<string,mixed>> $prices
     * @return array<int,mixed> failed items (empty = all deleted)
     */
    public function deleteSpecialPrices(array $prices): array
    {
        return (array) $this->http->post('V1/products/special-price-delete', ['prices' => array_values($prices)]);
    }

    /**
     * Fetch the special prices currently set for the given SKUs.
     *
     * @param list<string> $skus
     * @return list<array<string,mixed>>
     */
    public function specialPrices(array $skus): array
    {
        return (array) $this->http->post('V1/products/special-price-information', ['skus' => array_values($skus)]);
    }
}
