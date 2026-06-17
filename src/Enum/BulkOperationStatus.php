<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Enum;

/**
 * Per-operation status codes returned by the bulk detailed-status endpoint
 * (`rest/V1/bulk/<uuid>/detailed-status`), mirroring Magento's
 * \Magento\Framework\Bulk\OperationInterface constants.
 */
enum BulkOperationStatus: int
{
    case Complete           = 1;
    case RetriablyFailed    = 2;
    case NotRetriablyFailed = 3;
    case Open               = 4;
    case Rejected           = 5;

    /**
     * True once the operation has been processed (whether it succeeded or not).
     */
    public function isFinished(): bool
    {
        return $this !== self::Open;
    }

    /**
     * True while the operation is still queued/unprocessed.
     */
    public function isPending(): bool
    {
        return $this === self::Open;
    }

    public function isSuccess(): bool
    {
        return $this === self::Complete;
    }

    public function isFailure(): bool
    {
        return match ($this) {
            self::RetriablyFailed, self::NotRetriablyFailed, self::Rejected => true,
            default => false,
        };
    }
}
