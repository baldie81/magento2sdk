<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Support;

/**
 * A value object that can render itself as the array payload Magento expects.
 * Every write method that accepts an object accepts `Arrayable|array`, so you
 * can pass a builder or a raw array interchangeably.
 */
interface Arrayable
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(): array;
}
