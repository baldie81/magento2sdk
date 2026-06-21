<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Resource;

use Baldie81\Magento2SDK\Support\SearchCriteria;

/**
 * Attribute sets endpoint (`rest/V1/products/attribute-sets`).
 */
final class AttributeSets extends AbstractResource
{
    /**
     * List attribute sets for an entity type (4 = catalog_product).
     *
     * @return list<array{attribute_set_id?:int,attribute_set_name?:string}>
     */
    public function all(int $entityTypeId = 4): array
    {
        $criteria = SearchCriteria::create()
            ->where('entity_type_id', $entityTypeId)
            ->pageSize(200);

        $response = (array) $this->http->get('V1/eav/attribute-sets/list', $criteria->toArray());

        return $response['items'] ?? [];
    }

    /**
     * List the attribute groups of a set (each carries an attribute_group_id you
     * can assign attributes into).
     *
     * @return list<array{attribute_group_id?:int,attribute_group_name?:string,attribute_set_id?:int}>
     */
    public function groups(int $attributeSetId): array
    {
        $criteria = SearchCriteria::create()
            ->where('attribute_set_id', $attributeSetId)
            ->pageSize(200);

        $response = (array) $this->http->get('V1/products/attribute-sets/groups/list', $criteria->toArray());

        return $response['items'] ?? [];
    }

    /**
     * List the attributes currently assigned to a set.
     *
     * @return list<array{attribute_code?:string}>
     */
    public function attributes(int $attributeSetId): array
    {
        return (array) ($this->http->get("V1/products/attribute-sets/{$attributeSetId}/attributes") ?? []);
    }

    /**
     * Assign an existing attribute to a set/group.
     *
     * @return int the assigned attribute id
     */
    public function assignAttribute(
        int $attributeSetId,
        int $attributeGroupId,
        string $attributeCode,
        int $sortOrder = 0,
    ): int {
        return (int) $this->http->post('V1/products/attribute-sets/attributes', [
            'attributeSetId'   => $attributeSetId,
            'attributeGroupId' => $attributeGroupId,
            'attributeCode'    => $attributeCode,
            'sortOrder'        => $sortOrder,
        ]);
    }

    /**
     * Create a new attribute set, cloned from a skeleton set (4 = Default).
     *
     * @return array<string,mixed> the created set (carries attribute_set_id)
     */
    public function create(string $name, int $skeletonId = 4, int $sortOrder = 0): array
    {
        return (array) $this->http->post('V1/products/attribute-sets', [
            'attributeSet' => [
                'attribute_set_name' => $name,
                'sort_order'         => $sortOrder,
            ],
            'skeletonId' => $skeletonId,
        ]);
    }

    /**
     * Delete an attribute set (also removes its groups).
     */
    public function delete(int $attributeSetId): bool
    {
        return (bool) $this->http->delete("V1/products/attribute-sets/{$attributeSetId}");
    }

    /**
     * Create an attribute group within a set.
     *
     * @return array<string,mixed> the created group (carries attribute_group_id)
     */
    public function createGroup(int $attributeSetId, string $name): array
    {
        return (array) $this->http->post('V1/products/attribute-sets/groups', [
            'group' => [
                'attribute_group_name' => $name,
                'attribute_set_id'     => $attributeSetId,
            ],
        ]);
    }

    /**
     * Delete an attribute group.
     */
    public function deleteGroup(int $attributeGroupId): bool
    {
        return (bool) $this->http->delete("V1/products/attribute-sets/groups/{$attributeGroupId}");
    }

    /**
     * Set a group's position within its set. Magento only accepts the position as
     * `sort_order` under `extension_attributes` (a top-level `sort_order` is
     * rejected); lower sorts first. The name is required by the save endpoint.
     *
     * @return array<string,mixed>
     */
    public function setGroupSortOrder(int $attributeGroupId, int $attributeSetId, string $name, int $sortOrder): array
    {
        return (array) $this->http->post('V1/products/attribute-sets/groups', [
            'group' => [
                'attribute_group_id'   => $attributeGroupId,
                'attribute_group_name' => $name,
                'attribute_set_id'     => $attributeSetId,
                'extension_attributes' => ['sort_order' => $sortOrder],
            ],
        ]);
    }
}
