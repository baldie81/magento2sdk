<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Resource;

use Baldie81\Magento2SDK\Support\Category;
use Baldie81\Magento2SDK\Support\SearchCriteria;

/**
 * Categories endpoint (`rest/V1/categories`).
 */
final class Categories extends AbstractResource
{
    /**
     * Fetch the category tree (optionally from a root id and bounded by depth).
     *
     * @return array<string,mixed>
     */
    public function tree(?int $rootCategoryId = null, ?int $depth = null): array
    {
        $query = [];
        if ($rootCategoryId !== null) {
            $query['rootCategoryId'] = $rootCategoryId;
        }
        if ($depth !== null) {
            $query['depth'] = $depth;
        }

        return (array) $this->http->get('V1/categories', $query);
    }

    /**
     * @return array<string,mixed> the category entity
     */
    public function get(int $categoryId): array
    {
        return (array) $this->http->get("V1/categories/{$categoryId}");
    }

    /**
     * Flat category list, handy for resolving category paths to ids. Paginates
     * through every page (Magento caps the page size, so a single request would
     * truncate large trees). Defaults to fetching id/name/parent_id.
     *
     * @return list<array{id?:int,name?:string,parent_id?:int}>
     */
    public function listFlat(?SearchCriteria $criteria = null): array
    {
        $criteria ??= SearchCriteria::create()
            ->fields('items[id,name,parent_id]', 'total_count');

        return $this->paginateItems('V1/categories/list', $criteria);
    }

    /**
     * Create a category. Accepts a {@see Category} builder or a raw array (the
     * inner category body, without the "category" wrapper).
     *
     * @param Category|array<string,mixed> $category
     * @return array<string,mixed>
     */
    public function create(Category|array $category): array
    {
        return (array) $this->http->post('V1/categories', ['category' => $this->payload($category)]);
    }

    /**
     * @param Category|array<string,mixed> $category
     * @return array<string,mixed>
     */
    public function update(int $categoryId, Category|array $category): array
    {
        return (array) $this->http->put("V1/categories/{$categoryId}", ['category' => $this->payload($category)]);
    }

    public function delete(int $categoryId): bool
    {
        return (bool) $this->http->delete("V1/categories/{$categoryId}");
    }

    /**
     * Assign a product to a category (optionally at a given position).
     */
    public function assignProduct(int $categoryId, string $sku, ?int $position = null): bool
    {
        $productLink = ['sku' => $sku, 'category_id' => (string) $categoryId];
        if ($position !== null) {
            $productLink['position'] = $position;
        }

        return (bool) $this->http->post(
            "V1/categories/{$categoryId}/products",
            ['productLink' => $productLink],
        );
    }

    /**
     * Remove a product from a category.
     */
    public function removeProduct(int $categoryId, string $sku): bool
    {
        return (bool) $this->http->delete("V1/categories/{$categoryId}/products/" . $this->segment($sku));
    }
}
