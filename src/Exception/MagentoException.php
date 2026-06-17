<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Exception;

use RuntimeException;

/**
 * Base type for every exception thrown by the SDK. Catch this to handle any
 * SDK failure regardless of cause.
 */
class MagentoException extends RuntimeException
{
}
