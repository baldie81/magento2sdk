<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Enum;

/**
 * Product visibility, mirroring Magento's
 * \Magento\Catalog\Model\Product\Visibility.
 */
enum Visibility: int
{
    case NotVisibleIndividually = 1;
    case Catalog                = 2;
    case Search                 = 3;
    case CatalogSearch          = 4;
}
