<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Support;

/**
 * Fluent builder for a stock item payload.
 *
 *   StockItem::make()->qty(12)->inStock(true);
 *   StockItem::ofQuantity(12);   // is_in_stock derived from the quantity
 */
final class StockItem implements Arrayable
{
    /** @var array<string,mixed> */
    private array $fields = [];

    public static function make(): self
    {
        return new self();
    }

    /**
     * Quantity with is_in_stock derived from it (unless overridden).
     */
    public static function ofQuantity(float $quantity, ?bool $inStock = null): self
    {
        return self::make()->qty($quantity)->inStock($inStock ?? $quantity > 0);
    }

    public function qty(float $quantity): self
    {
        $this->fields['qty'] = $quantity;

        return $this;
    }

    public function inStock(bool $inStock): self
    {
        $this->fields['is_in_stock'] = $inStock;

        return $this;
    }

    public function set(string $field, mixed $value): self
    {
        $this->fields[$field] = $value;

        return $this;
    }

    public function toArray(): array
    {
        return $this->fields;
    }
}
