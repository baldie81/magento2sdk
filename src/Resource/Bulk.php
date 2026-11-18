<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Resource;

use GuzzleHttp\Promise\Utils;
use Baldie81\Magento2SDK\Exception\ApiException;
use Baldie81\Magento2SDK\Exception\MagentoException;
use Baldie81\Magento2SDK\Support\Product;

/**
 * Asynchronous bulk API (`rest/async/bulk/V1/...` and `rest/V1/bulk/...`).
 *
 * Bulk requests return only a `bulk_uuid`; the work is processed by Magento's
 * message-queue consumers, so poll {@see status()}/{@see detailedStatus()} to
 * learn the outcome.
 */
final class Bulk extends AbstractResource
{
    /**
     * Queue one batch of product saves (create or update; Magento upserts by SKU).
     * Each item may be a {@see Product} builder or a raw {"product": {...}} payload.
     *
     * @param list<Product|array<string,mixed>> $products
     * @return array<string,mixed> decoded response (bulk_uuid, request_items, errors)
     */
    public function createProducts(array $products): array
    {
        return (array) $this->http->post('async/bulk/V1/products', $this->wrapProducts($products));
    }

    /**
     * Queue many product batches concurrently, firing them in waves of
     * `$concurrency` via Guzzle promises so uuids are collected without blocking
     * on each batch in turn. Results are keyed by the input chunk index; each is
     * either ['bulk_uuid' => string|null] or ['error' => string].
     *
     * @param list<list<Product|array<string,mixed>>> $chunks
     * @return array<int,array{bulk_uuid?:string|null,error?:string}>
     */
    public function dispatchProducts(array $chunks, int $concurrency = 4): array
    {
        $results = [];

        foreach (array_chunk($chunks, max(1, $concurrency), true) as $wave) {
            $promises = [];
            foreach ($wave as $i => $chunk) {
                $promises[$i] = $this->http->postAsync('async/bulk/V1/products', $this->wrapProducts($chunk));
            }

            foreach (Utils::settle($promises)->wait() as $i => $outcome) {
                if (($outcome['state'] ?? '') === 'fulfilled') {
                    try {
                        $body        = $this->http->decode($outcome['value']);
                        $results[$i] = ['bulk_uuid' => is_array($body) ? ($body['bulk_uuid'] ?? null) : null];
                    } catch (MagentoException $e) {
                        $results[$i] = ['error' => $e->getMessage()];
                    }
                } else {
                    $results[$i] = ['error' => ApiException::describe($outcome['reason'] ?? null)];
                }
            }
        }

        ksort($results);

        return $results;
    }

    /**
     * Wrap each product for the bulk endpoint: a {@see Product} becomes a
     * {"product": {...}} entry; a raw array is assumed already in that shape.
     *
     * @param list<Product|array<string,mixed>> $products
     * @return list<array<string,mixed>>
     */
    private function wrapProducts(array $products): array
    {
        $wrapped = [];
        foreach ($products as $product) {
            $wrapped[] = $product instanceof Product ? ['product' => $product->toArray()] : $product;
        }

        return $wrapped;
    }

    /**
     * Summary status of a bulk (operations counts by state).
     *
     * @return array<string,mixed>
     */
    public function status(string $bulkUuid): array
    {
        return (array) $this->http->get('V1/bulk/' . $this->segment($bulkUuid) . '/status');
    }

    /**
     * Per-operation outcomes (status + result_message), so callers can tell
     * COMPLETE from FAILED/REJECTED once the bulk drains.
     *
     * @return array<string,mixed>
     */
    public function detailedStatus(string $bulkUuid): array
    {
        return (array) $this->http->get('V1/bulk/' . $this->segment($bulkUuid) . '/detailed-status');
    }
}
