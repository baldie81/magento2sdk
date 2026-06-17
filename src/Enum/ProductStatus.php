<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Enum;

/**
 * Product status, mirroring Magento's
 * \Magento\Catalog\Model\Product\Attribute\Source\Status (the `status`
 * attribute accepts only these two values).
 */
enum ProductStatus: int
{
    case Enabled  = 1;
    case Disabled = 2;

    public function isEnabled(): bool
    {
        return $this === self::Enabled;
    }

    public function isDisabled(): bool
    {
        return $this === self::Disabled;
    }
}
