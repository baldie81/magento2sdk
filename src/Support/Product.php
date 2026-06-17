<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Support;

use Baldie81\Magento2SDK\Enum\ProductStatus;
use Baldie81\Magento2SDK\Enum\Visibility;

/**
 * Fluent builder for a product payload (the inner "product" body — resources and
 * the bulk API add the wrapper). Unset fields are simply omitted, so the same
 * builder works for both create and partial update.
 *
 *   Product::make('SKU-1')
 *       ->name('Example')->attributeSetId(4)->type('simple')
 *       ->price(19.90)->status(ProductStatus::Enabled)->visibility(Visibility::CatalogSearch)
 *       ->websiteIds([1])
 *       ->customAttribute('color', 49)
 *       ->mediaGalleryEntry(MediaEntry::image($b64, 'image/jpeg', 'front.jpg'));
 */
final class Product implements Arrayable
{
    /** @var array<string,mixed> */
    private array $fields = [];

    /** @var array<string,mixed> */
    private array $customAttributes = [];

    /** @var array<string,mixed> */
    private array $extensionAttributes = [];

    /** @var list<int>|null */
    private ?array $websiteIds = null;

    /** @var list<array<string,mixed>> */
    private array $mediaGalleryEntries = [];

    /** @var list<array<string,mixed>> */
    private array $configurableOptions = [];

    /** @var list<int>|null */
    private ?array $configurableLinks = null;

    public function __construct(string $sku)
    {
        $this->fields['sku'] = $sku;
    }

    public static function make(string $sku): self
    {
        return new self($sku);
    }

    public function name(string $name): self
    {
        $this->fields['name'] = $name;

        return $this;
    }

    public function attributeSetId(int $attributeSetId): self
    {
        $this->fields['attribute_set_id'] = $attributeSetId;

        return $this;
    }

    public function type(string $typeId): self
    {
        $this->fields['type_id'] = $typeId;

        return $this;
    }

    public function price(float $price): self
    {
        $this->fields['price'] = $price;

        return $this;
    }

    public function weight(float $weight): self
    {
        $this->fields['weight'] = $weight;

        return $this;
    }

    public function visibility(Visibility|int $visibility): self
    {
        $this->fields['visibility'] = $visibility instanceof Visibility ? $visibility->value : $visibility;

        return $this;
    }

    public function status(ProductStatus|int $status): self
    {
        $this->fields['status'] = $status instanceof ProductStatus ? $status->value : $status;

        return $this;
    }

    /**
     * @param list<int> $websiteIds
     */
    public function websiteIds(array $websiteIds): self
    {
        $this->websiteIds = array_values($websiteIds);

        return $this;
    }

    public function customAttribute(string $code, mixed $value): self
    {
        $this->customAttributes[$code] = $value;

        return $this;
    }

    /**
     * @param array<string,mixed> $attributes code => value
     */
    public function customAttributes(array $attributes): self
    {
        foreach ($attributes as $code => $value) {
            $this->customAttributes[$code] = $value;
        }

        return $this;
    }

    public function extensionAttribute(string $key, mixed $value): self
    {
        $this->extensionAttributes[$key] = $value;

        return $this;
    }

    /**
     * Attach a media gallery entry (image, etc.). Accepts a {@see MediaEntry}
     * builder or a raw array.
     *
     * @param MediaEntry|array<string,mixed> $entry
     */
    public function mediaGalleryEntry(MediaEntry|array $entry): self
    {
        $this->mediaGalleryEntries[] = $entry instanceof Arrayable ? $entry->toArray() : $entry;

        return $this;
    }

    /**
     * @param list<MediaEntry|array<string,mixed>> $entries
     */
    public function media(array $entries): self
    {
        foreach ($entries as $entry) {
            $this->mediaGalleryEntry($entry);
        }

        return $this;
    }

    /**
     * Define a configurable option axis (e.g. colour or size). The value indexes
     * are the option ids the axis spans.
     *
     * @param list<int> $valueIndexes
     */
    public function configurableOption(int $attributeId, array $valueIndexes, string $label = ''): self
    {
        $this->configurableOptions[] = [
            'attribute_id' => (string) $attributeId,
            'label'        => $label,
            'values'       => array_map(
                static fn (int $v) => ['value_index' => $v],
                array_values($valueIndexes),
            ),
        ];

        return $this;
    }

    /**
     * Link child products to this configurable, by their Magento entity ids.
     *
     * @param list<int> $productIds
     */
    public function configurableLinks(array $productIds): self
    {
        $this->configurableLinks = array_values($productIds);

        return $this;
    }

    /**
     * Escape hatch for any top-level product field not covered above.
     */
    public function set(string $field, mixed $value): self
    {
        $this->fields[$field] = $value;

        return $this;
    }

    public function toArray(): array
    {
        $product = $this->fields;

        $extension = $this->extensionAttributes;
        if ($this->websiteIds !== null) {
            $extension['website_ids'] = $this->websiteIds;
        }
        if ($this->configurableOptions !== []) {
            $extension['configurable_product_options'] = $this->configurableOptions;
        }
        if ($this->configurableLinks !== null) {
            $extension['configurable_product_links'] = $this->configurableLinks;
        }
        if ($extension !== []) {
            $product['extension_attributes'] = $extension;
        }

        if ($this->customAttributes !== []) {
            $product['custom_attributes'] = [];
            foreach ($this->customAttributes as $code => $value) {
                $product['custom_attributes'][] = ['attribute_code' => $code, 'value' => $value];
            }
        }

        if ($this->mediaGalleryEntries !== []) {
            $product['media_gallery_entries'] = $this->mediaGalleryEntries;
        }

        return $product;
    }
}
