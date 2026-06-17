<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Support;

/**
 * A selectable-attribute option with optional per-store-view labels.
 *
 * Magento localizes option labels per **store view**: `label` is the admin /
 * default-scope label, and each entry in `store_labels` overrides it for one
 * store view (i.e. one language). Store views are addressed by numeric id —
 * Magento has no notion of locale codes at this endpoint.
 *
 *   AttributeOption::create('Red')
 *       ->withStoreLabel(1, 'Κόκκινο')   // Greek store view
 *       ->withStoreLabel(2, 'Rouge');    // French store view
 */
final class AttributeOption implements Arrayable
{
    /** @var array<int,string> store_id => label */
    private array $storeLabels = [];

    public function __construct(
        private readonly string $label,
        private readonly ?int $sortOrder = null,
        private readonly bool $isDefault = false,
        private readonly ?string $value = null,
    ) {}

    /**
     * @param string|null $value an existing option id, to update its labels
     */
    public static function create(
        string $label,
        ?int $sortOrder = null,
        bool $isDefault = false,
        ?string $value = null,
    ): self {
        return new self($label, $sortOrder, $isDefault, $value);
    }

    /**
     * The admin / default-scope label (what idempotent lookups match against).
     */
    public function label(): string
    {
        return $this->label;
    }

    /**
     * Set the label for a single store view (language). Returns a new instance.
     */
    public function withStoreLabel(int $storeId, string $label): self
    {
        $clone = clone $this;
        $clone->storeLabels[$storeId] = $label;

        return $clone;
    }

    /**
     * Set several store-view labels at once. Returns a new instance.
     *
     * @param array<int,string> $labels store_id => label
     */
    public function withStoreLabels(array $labels): self
    {
        $clone = clone $this;
        foreach ($labels as $storeId => $label) {
            $clone->storeLabels[(int) $storeId] = $label;
        }

        return $clone;
    }

    /**
     * @return array<string,mixed> the Magento `option` payload
     */
    public function toArray(): array
    {
        $option = ['label' => $this->label];

        if ($this->value !== null) {
            $option['value'] = $this->value;
        }
        if ($this->sortOrder !== null) {
            $option['sort_order'] = $this->sortOrder;
        }
        if ($this->isDefault) {
            $option['is_default'] = true;
        }
        if ($this->storeLabels !== []) {
            foreach ($this->storeLabels as $storeId => $label) {
                $option['store_labels'][] = ['store_id' => $storeId, 'label' => $label];
            }
        }

        return $option;
    }
}
