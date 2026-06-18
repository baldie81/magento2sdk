<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Resource;

use Baldie81\Magento2SDK\Support\Arrayable;
use Baldie81\Magento2SDK\Support\OrderStatus;
use Baldie81\Magento2SDK\Support\SearchCriteria;

/**
 * Sales orders.
 *
 * Reads and creation go through the order repository (`rest/V1/orders`);
 * invoicing and shipping go through the (singular) action endpoints
 * `rest/V1/order/{id}/invoice` and `rest/V1/order/{id}/ship` — a Magento
 * naming quirk preserved here.
 */
final class Orders extends AbstractResource
{
    /**
     * @return array<string,mixed> the order entity
     */
    public function get(int $orderId): array
    {
        return (array) $this->http->get("V1/orders/{$orderId}");
    }

    /**
     * Fetch an order's current status as an {@see OrderStatus} value object —
     * Magento returns the admin-configurable status code, which {@see OrderStatus}
     * pairs with a label and, where it coincides with a system state, an
     * {@see \Baldie81\Magento2SDK\Enum\OrderState} (via {@see OrderStatus::state()}).
     */
    public function getStatus(int $orderId): OrderStatus
    {
        return OrderStatus::of((string) $this->http->get("V1/orders/{$orderId}/statuses"));
    }

    /**
     * Run an order search for a single page. Returns the full Magento envelope
     * (items, search_criteria, total_count). Use {@see searchAll()} for every
     * page.
     *
     * @return array<string,mixed>
     */
    public function search(SearchCriteria $criteria): array
    {
        return (array) $this->http->get('V1/orders', $criteria->toArray());
    }

    /**
     * Like {@see search()} but paginates and returns every matching order
     * (merged `items`), working around Magento's page-size cap.
     *
     * @return list<array<string,mixed>>
     */
    public function searchAll(SearchCriteria $criteria, int $pageSize = self::MAX_PAGE_SIZE): array
    {
        return $this->paginateItems('V1/orders', $criteria, $pageSize);
    }

    /**
     * Save an order through the order repository. Accepts an {@see Arrayable}
     * builder or a raw array (the inner order entity, without the "entity"
     * wrapper). Returns the saved order entity.
     *
     * @param Arrayable|array<string,mixed> $order
     * @return array<string,mixed>
     */
    public function create(Arrayable|array $order): array
    {
        return (array) $this->http->post('V1/orders', ['entity' => $this->payload($order)]);
    }

    /**
     * Invoice an order. With no items the whole order is invoiced; otherwise
     * pass `[['order_item_id' => 1, 'qty' => 2], ...]` to invoice partially.
     * `$capture` captures payment online (set false for offline/authorize-only
     * payments). Returns the new invoice id.
     *
     * @param list<array<string,mixed>> $items
     */
    public function invoice(
        int $orderId,
        array $items = [],
        bool $capture = true,
        bool $notify = false,
        ?string $comment = null,
    ): int {
        $body = ['capture' => $capture, 'notify' => $notify];
        if ($items !== []) {
            $body['items'] = array_values($items);
        }
        if ($comment !== null) {
            $body['appendComment'] = true;
            $body['comment']       = ['comment' => $comment];
        }

        return (int) $this->http->post("V1/order/{$orderId}/invoice", $body);
    }

    /**
     * Ship an order. With no items the whole order is shipped; otherwise pass
     * `[['order_item_id' => 1, 'qty' => 2], ...]`. Tracking entries are
     * `['track_number' => '…', 'title' => '…', 'carrier_code' => '…']`. Returns
     * the new shipment id.
     *
     * @param list<array<string,mixed>> $items
     * @param list<array<string,mixed>> $tracks
     */
    public function ship(
        int $orderId,
        array $items = [],
        array $tracks = [],
        bool $notify = false,
        ?string $comment = null,
    ): int {
        $body = ['notify' => $notify];
        if ($items !== []) {
            $body['items'] = array_values($items);
        }
        if ($tracks !== []) {
            $body['tracks'] = array_values($tracks);
        }
        if ($comment !== null) {
            $body['appendComment'] = true;
            $body['comment']       = ['comment' => $comment];
        }

        return (int) $this->http->post("V1/order/{$orderId}/ship", $body);
    }

    /**
     * Cancel an order. Returns whether Magento accepted the cancellation (it
     * refuses orders that are already invoiced/shipped/complete).
     */
    public function cancel(int $orderId): bool
    {
        return (bool) $this->http->post("V1/orders/{$orderId}/cancel");
    }
}
