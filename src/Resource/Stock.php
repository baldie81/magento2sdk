<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Resource;

use Baldie81\Magento2SDK\Support\StockItem;

/**
 * Stock / inventory updates.
 *
 * Covers the legacy single-SKU stock item (`rest/V1/products/{sku}/stockItems`)
 * and the Multi-Source Inventory source items (`rest/V1/inventory/source-items`).
 */
final class Stock extends AbstractResource
{
    /**
     * Fetch the (legacy) stock item for a SKU.
     *
     * @return array<string,mixed>
     */
    public function get(string $sku): array
    {
        return (array) $this->http->get('V1/stockItems/' . $this->segment($sku));
    }

    /**
     * Update a SKU's stock item. Returns the stock item id.
     *
     * @param StockItem|array<string,mixed> $stockItem e.g. ['qty' => 12, 'is_in_stock' => true]
     */
    public function update(string $sku, StockItem|array $stockItem, int $itemId = 0): int
    {
        return $this->id($this->http->put(
            'V1/products/' . $this->segment($sku) . '/stockItems/' . $itemId,
            ['stockItem' => $this->payload($stockItem)],
        ), 'the stock item id');
    }

    /**
     * Convenience: set a SKU's quantity (and, by default, derive is_in_stock
     * from whether the quantity is positive).
     */
    public function setQuantity(string $sku, float $quantity, ?bool $isInStock = null): int
    {
        return $this->update($sku, [
            'qty'         => $quantity,
            'is_in_stock' => $isInStock ?? $quantity > 0,
        ]);
    }

    /**
     * Create/update MSI source items. Each item is
     * ['sku' => …, 'source_code' => 'default', 'quantity' => …, 'status' => 1].
     *
     * @param list<array<string,mixed>> $sourceItems
     */
    public function saveSourceItems(array $sourceItems): void
    {
        $this->http->post('V1/inventory/source-items', ['sourceItems' => array_values($sourceItems)]);
    }

    /**
     * Delete MSI source items. Each item is ['sku' => …, 'source_code' => …].
     *
     * @param list<array<string,mixed>> $sourceItems
     */
    public function deleteSourceItems(array $sourceItems): void
    {
        $this->http->post('V1/inventory/source-items-delete', ['sourceItems' => array_values($sourceItems)]);
    }
}
