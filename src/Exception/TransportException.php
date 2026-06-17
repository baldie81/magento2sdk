<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Exception;

/**
 * A transport-level failure with no usable HTTP response: DNS/connection
 * errors, timeouts, TLS failures, etc.
 */
final class TransportException extends MagentoException
{
}
