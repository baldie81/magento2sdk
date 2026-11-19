<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Resource;

/**
 * Configurable products: link simple children to a configurable parent and
 * manage its option axes (`rest/V1/configurable-products/...`).
 */
final class ConfigurableProducts extends AbstractResource
{
    /**
     * List the simple children currently linked to a configurable.
     *
     * @return list<array<string,mixed>>
     */
    public function children(string $sku): array
    {
        return $this->rows($this->http->get('V1/configurable-products/' . $this->segment($sku) . '/children'));
    }

    /**
     * Link a simple product to a configurable parent. The child must already
     * carry option values for the parent's axes.
     */
    public function addChild(string $sku, string $childSku): bool
    {
        return (bool) $this->http->post(
            'V1/configurable-products/' . $this->segment($sku) . '/child',
            ['childSku' => $childSku],
        );
    }

    /**
     * Unlink a simple product from a configurable parent.
     */
    public function removeChild(string $sku, string $childSku): bool
    {
        return (bool) $this->http->delete(
            'V1/configurable-products/' . $this->segment($sku) . '/children/' . $this->segment($childSku),
        );
    }

    /**
     * List the option axes (e.g. size, color) defined on a configurable.
     *
     * @return list<array<string,mixed>>
     */
    public function options(string $sku): array
    {
        return $this->rows($this->http->get('V1/configurable-products/' . $this->segment($sku) . '/options/all'));
    }

    /**
     * Define an option axis on a configurable. Returns the new option id. The
     * option carries `attribute_id`, `label` and the `values` (option ids) it
     * spans, e.g.
     *   ['attribute_id' => '93', 'label' => 'Size',
     *    'values' => [['value_index' => 167], ['value_index' => 168]]]
     *
     * @param array<string,mixed> $option
     */
    public function addOption(string $sku, array $option): int
    {
        return $this->id($this->http->post(
            'V1/configurable-products/' . $this->segment($sku) . '/options',
            ['option' => $option],
        ), 'the new option id');
    }

    public function deleteOption(string $sku, int $optionId): bool
    {
        return (bool) $this->http->delete(
            'V1/configurable-products/' . $this->segment($sku) . '/options/' . $optionId,
        );
    }
}
