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
}
