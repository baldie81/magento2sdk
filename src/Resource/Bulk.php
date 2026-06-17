<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Resource;

use GuzzleHttp\Promise\Utils;
use Baldie81\Magento2SDK\Exception\ApiException;

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
     *
     * @param list<array<string,mixed>> $products each item a {"product": {...}} payload
     * @return array<string,mixed> decoded response (bulk_uuid, request_items, errors)
     */
    public function createProducts(array $products): array
    {
        return (array) $this->http->post('async/bulk/V1/products', array_values($products));
    }

    /**
     * Queue many product batches concurrently, firing them in waves of
     * `$concurrency` via Guzzle promises so uuids are collected without blocking
     * on each batch in turn. Results are keyed by the input chunk index; each is
     * either ['bulk_uuid' => string|null] or ['error' => string].
     *
     * @param list<list<array<string,mixed>>> $chunks
     * @return array<int,array{bulk_uuid?:string|null,error?:string}>
     */
    public function dispatchProducts(array $chunks, int $concurrency = 4): array
    {
        $results = [];

        foreach (array_chunk($chunks, max(1, $concurrency), true) as $wave) {
            $promises = [];
            foreach ($wave as $i => $chunk) {
                $promises[$i] = $this->http->postAsync('async/bulk/V1/products', array_values($chunk));
            }

            foreach (Utils::settle($promises)->wait() as $i => $outcome) {
                if (($outcome['state'] ?? '') === 'fulfilled') {
                    $body        = $this->http->decode($outcome['value']);
                    $results[$i] = ['bulk_uuid' => is_array($body) ? ($body['bulk_uuid'] ?? null) : null];
                } else {
                    $results[$i] = ['error' => ApiException::describe($outcome['reason'] ?? null)];
                }
            }
        }

        ksort($results);

        return $results;
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
