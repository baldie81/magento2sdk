<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Support;

/**
 * A price for one SKU/store, usable for both base prices and special (sale)
 * prices — set `from()`/`to()` for a special price window.
 *
 *   Price::make('SKU-1', 19.90);                       // base price, default store
 *   Price::make('SKU-1', 14.90)->from('2026-06-01 00:00:00');  // special price
 */
final class Price implements Arrayable
{
    private ?string $from = null;
    private ?string $to = null;

    public function __construct(
        private readonly string $sku,
        private readonly float $price,
        private readonly int $storeId = 0,
    ) {}

    public static function make(string $sku, float $price, int $storeId = 0): self
    {
        return new self($sku, $price, $storeId);
    }

    public function from(string $datetime): self
    {
        $this->from = $datetime;

        return $this;
    }

    public function to(string $datetime): self
    {
        $this->to = $datetime;

        return $this;
    }

    public function toArray(): array
    {
        $price = [
            'sku'      => $this->sku,
            'price'    => $this->price,
            'store_id' => $this->storeId,
        ];
        if ($this->from !== null) {
            $price['price_from'] = $this->from;
        }
        if ($this->to !== null) {
            $price['price_to'] = $this->to;
        }

        return $price;
    }
}
