<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Resource;

use Baldie81\Magento2SDK\Support\MediaEntry;

/**
 * Product media gallery entries (`rest/V1/products/{sku}/media`).
 */
final class ProductMedia extends AbstractResource
{
    /**
     * List a product's media gallery entries.
     *
     * @return list<array<string,mixed>>
     */
    public function entries(string $sku): array
    {
        return $this->rows($this->http->get('V1/products/' . $this->segment($sku) . '/media'));
    }

    /**
     * @return array<string,mixed>
     */
    public function get(string $sku, int $entryId): array
    {
        return (array) $this->http->get('V1/products/' . $this->segment($sku) . '/media/' . $entryId);
    }

    /**
     * Add a gallery entry. Returns the new entry id. The entry typically carries
     * `media_type`, `label`, `types`, `disabled`, and a base64 `content` block:
     *   ['media_type' => 'image', 'label' => 'Front', 'types' => ['image'],
     *    'content' => ['base64_encoded_data' => …, 'type' => 'image/jpeg', 'name' => 'front.jpg']]
     *
     * Accepts a {@see MediaEntry} builder or a raw array.
     *
     * @param MediaEntry|array<string,mixed> $entry
     */
    public function create(string $sku, MediaEntry|array $entry): int
    {
        return (int) $this->http->post(
            'V1/products/' . $this->segment($sku) . '/media',
            ['entry' => $this->payload($entry)],
        );
    }

    /**
     * @param MediaEntry|array<string,mixed> $entry
     */
    public function update(string $sku, int $entryId, MediaEntry|array $entry): bool
    {
        return (bool) $this->http->put(
            'V1/products/' . $this->segment($sku) . '/media/' . $entryId,
            ['entry' => $this->payload($entry)],
        );
    }

    public function delete(string $sku, int $entryId): bool
    {
        return (bool) $this->http->delete('V1/products/' . $this->segment($sku) . '/media/' . $entryId);
    }
}
