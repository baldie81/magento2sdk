<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Support;

/**
 * Fluent builder for a category payload (the inner "category" body).
 *
 *   Category::make('Shoes', parentId: 2)->isActive(true)
 *       ->customAttribute('description', '…');
 */
final class Category implements Arrayable
{
    /** @var array<string,mixed> */
    private array $fields = [];

    /** @var array<string,mixed> */
    private array $customAttributes = [];

    public static function make(string $name, ?int $parentId = null): self
    {
        $category = new self();
        $category->fields['name'] = $name;
        if ($parentId !== null) {
            $category->fields['parent_id'] = $parentId;
        }

        return $category;
    }

    public function name(string $name): self
    {
        $this->fields['name'] = $name;

        return $this;
    }

    public function parentId(int $parentId): self
    {
        $this->fields['parent_id'] = $parentId;

        return $this;
    }

    public function isActive(bool $isActive): self
    {
        $this->fields['is_active'] = $isActive;

        return $this;
    }

    public function position(int $position): self
    {
        $this->fields['position'] = $position;

        return $this;
    }

    public function customAttribute(string $code, mixed $value): self
    {
        $this->customAttributes[$code] = $value;

        return $this;
    }

    public function set(string $field, mixed $value): self
    {
        $this->fields[$field] = $value;

        return $this;
    }

    public function toArray(): array
    {
        $category = $this->fields;

        if ($this->customAttributes !== []) {
            $category['custom_attributes'] = [];
            foreach ($this->customAttributes as $code => $value) {
                $category['custom_attributes'][] = ['attribute_code' => $code, 'value' => $value];
            }
        }

        return $category;
    }
}
