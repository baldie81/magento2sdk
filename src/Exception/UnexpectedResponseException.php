<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Exception;

/**
 * Magento answered successfully, but not with what the endpoint is documented
 * to return (e.g. an object where the new entity's id was expected). Thrown
 * instead of coercing the response into a plausible-looking but wrong value.
 *
 * The request itself may well have been carried out — check the store before
 * retrying a write.
 */
final class UnexpectedResponseException extends MagentoException
{
    public function __construct(
        string $message,
        public readonly mixed $response = null,
    ) {
        parent::__construct($message);
    }
}
