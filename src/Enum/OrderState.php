<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Enum;

/**
 * Order state, mirroring Magento's \Magento\Sales\Model\Order state constants.
 *
 * This is the system **state** (a closed set), distinct from the order
 * **status**, which is admin-configurable and therefore left as a free string.
 */
enum OrderState: string
{
    case New            = 'new';
    case PendingPayment = 'pending_payment';
    case Processing     = 'processing';
    case Complete       = 'complete';
    case Closed         = 'closed';
    case Canceled       = 'canceled';
    case Holded         = 'holded';
    case PaymentReview  = 'payment_review';
}
