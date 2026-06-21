<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Resource;

use Baldie81\Magento2SDK\Enum\FrontendInput;
use Baldie81\Magento2SDK\Support\AttributeOption;
use Baldie81\Magento2SDK\Support\SearchCriteria;

/**
 * Product attributes endpoint (`rest/V1/products/attributes`).
 */
final class ProductAttributes extends AbstractResource
{
    /**
     * In-memory option maps, kept for this resource's lifetime so a run that
     * touches many products doesn't refetch an attribute's options each time.
     *
     * @var array<string,array<string,string>> attributeCode => (normalised label => option id)
     */
    private array $optionMaps = [];

    /**
     * @return array<string,mixed> attribute metadata (attribute_id, frontend_input, …)
     */
    public function get(string $attributeCode): array
    {
        return (array) $this->http->get('V1/products/attributes/' . $this->segment($attributeCode));
    }

    /**
     * @return list<array{label?:string,value?:string}>
     */
    public function options(string $attributeCode): array
    {
        return (array) ($this->http->get('V1/products/attributes/' . $this->segment($attributeCode) . '/options') ?? []);
    }

    /**
     * Create an attribute. Magento infers the backend type from `frontend_input`,
     * which may be given as a {@see FrontendInput} enum or its string value.
     *
     * @param array<string,mixed> $attribute
     * @return array<string,mixed>
     */
    public function create(array $attribute): array
    {
        if (($attribute['frontend_input'] ?? null) instanceof FrontendInput) {
            $attribute['frontend_input'] = $attribute['frontend_input']->value;
        }

        return (array) $this->http->post('V1/products/attributes', ['attribute' => $attribute]);
    }

    /**
     * Update an existing attribute (e.g. its label). The attribute code is added
     * to the payload automatically so Magento accepts the save.
     *
     * @param array<string,mixed> $attribute
     * @return array<string,mixed>
     */
    public function update(string $attributeCode, array $attribute): array
    {
        if (($attribute['frontend_input'] ?? null) instanceof FrontendInput) {
            $attribute['frontend_input'] = $attribute['frontend_input']->value;
        }

        $attribute['attribute_code'] ??= $attributeCode;

        return (array) $this->http->put(
            'V1/products/attributes/' . $this->segment($attributeCode),
            ['attribute' => $attribute],
        );
    }

    /**
     * Delete an attribute by code.
     */
    public function delete(string $attributeCode): bool
    {
        return (bool) $this->http->delete('V1/products/attributes/' . $this->segment($attributeCode));
    }

    /**
     * Add one option to a selectable attribute. Returns the new option id
     * (Magento returns it as a bare string, e.g. "id_5").
     *
     * Pass an {@see AttributeOption} for per-store-view (multi-language) labels,
     * or a raw array for full control.
     *
     * @param AttributeOption|array<string,mixed> $option
     */
    public function addOption(string $attributeCode, AttributeOption|array $option): string
    {
        $payload = $option instanceof AttributeOption ? $option->toArray() : $option;

        $id = (string) $this->http->post(
            'V1/products/attributes/' . $this->segment($attributeCode) . '/options',
            ['option' => $payload],
        );

        $this->rememberOption($attributeCode, $this->optionLabel($option), $id);

        return $id;
    }

    /**
     * Build a {normalized label => option id} map — the efficient primitive for
     * bulk idempotency (one call instead of a lookup per option). Keys are
     * normalized exactly as {@see findOption()} (lowercased + trimmed); Magento's
     * empty placeholder option is skipped; on a normalised collision (e.g.
     * "Black" and "black") the last wins.
     *
     * The map is fetched once per attribute and **cached in memory** for this
     * resource's lifetime; later find/ensure calls and options created here reuse
     * it. Pass $refresh to force a reload (e.g. if options changed elsewhere).
     *
     * @return array<string,string> normalised admin label => option id
     */
    public function optionMap(string $attributeCode, bool $refresh = false): array
    {
        if ($refresh || !array_key_exists($attributeCode, $this->optionMaps)) {
            $map = [];
            foreach ($this->options($attributeCode) as $option) {
                if (!isset($option['label'], $option['value']) || (string) $option['value'] === '') {
                    continue;
                }
                $map[self::normalizeLabel((string) $option['label'])] = (string) $option['value'];
            }
            $this->optionMaps[$attributeCode] = $map;
        }

        return $this->optionMaps[$attributeCode];
    }

    /**
     * Drop the in-memory option cache — for one attribute, or all of them.
     */
    public function clearOptionCache(?string $attributeCode = null): void
    {
        if ($attributeCode === null) {
            $this->optionMaps = [];
        } else {
            unset($this->optionMaps[$attributeCode]);
        }
    }

    /**
     * Find an existing option's id by its admin (default-scope) label, matched
     * case-insensitively and trimmed — so "Black", "black" and "bLack" all
     * resolve to the same option. Returns null when none matches.
     */
    public function findOption(string $attributeCode, string $label): ?string
    {
        return $this->optionMap($attributeCode)[self::normalizeLabel($label)] ?? null;
    }

    /**
     * Idempotently ensure an option exists. If one already exists whose admin
     * label matches (case-insensitively) its id is returned unchanged; otherwise
     * the option is created. Lookup is by the option's default `label`, so
     * existing options are never duplicated.
     *
     * Note: an already-existing option is returned as-is — its store-view
     * (translation) labels are not updated.
     *
     * @param AttributeOption|array<string,mixed> $option
     */
    public function ensureOption(string $attributeCode, AttributeOption|array $option): string
    {
        return $this->findOption($attributeCode, $this->optionLabel($option))
            ?? $this->addOption($attributeCode, $option);
    }

    /**
     * Create the option, or — if one already exists with the same admin label —
     * update that option's labels (including its store-view translations) in
     * place. Returns the option id either way.
     *
     * Unlike {@see ensureOption()}, this refreshes the translations of an
     * existing option. The update is performed by re-posting the option with its
     * existing `value` (id) set, which Magento treats as an update rather than a
     * new option.
     *
     * @param AttributeOption|array<string,mixed> $option
     */
    public function upsertOption(string $attributeCode, AttributeOption|array $option): string
    {
        $existingId = $this->findOption($attributeCode, $this->optionLabel($option));

        if ($existingId === null) {
            return $this->addOption($attributeCode, $option);
        }

        $payload = $option instanceof AttributeOption ? $option->toArray() : $option;
        $payload['value'] = $existingId;
        $this->addOption($attributeCode, $payload);
        // The update endpoint's return isn't necessarily the id; keep the cache
        // pointing at the known existing id.
        $this->rememberOption($attributeCode, $this->optionLabel($option), $existingId);

        return $existingId;
    }

    /**
     * Idempotently ensure a whole set of options exists on an attribute, with a
     * single lookup of the existing options up front (instead of one per value).
     *
     * Each option may be an {@see AttributeOption}, a raw array, or a plain label
     * string. Existing options are matched by admin label case-insensitively and
     * left untouched; missing ones are created (with their store labels, if any).
     * Duplicates within the input — e.g. "Black", "black", "bLack" — collapse to
     * a single option.
     *
     * @param iterable<AttributeOption|array<string,mixed>|string> $options
     * @return array<string,string> the input label => option id, for every option requested
     */
    public function ensureOptions(string $attributeCode, iterable $options): array
    {
        $map    = $this->optionMap($attributeCode);
        $result = [];

        foreach ($options as $option) {
            $label = $this->optionLabel($option);
            if ($label === '') {
                continue;
            }
            $key = self::normalizeLabel($label);

            $map[$key] ??= $this->addOption($attributeCode, $this->asOption($option));
            $result[$label] = $map[$key];
        }

        return $result;
    }

    /**
     * @param AttributeOption|array<string,mixed>|string $option
     */
    private function optionLabel(AttributeOption|array|string $option): string
    {
        return match (true) {
            $option instanceof AttributeOption => $option->label(),
            is_string($option)                 => $option,
            default                            => (string) ($option['label'] ?? ''),
        };
    }

    /**
     * @param AttributeOption|array<string,mixed>|string $option
     * @return AttributeOption|array<string,mixed>
     */
    private function asOption(AttributeOption|array|string $option): AttributeOption|array
    {
        return is_string($option) ? AttributeOption::create($option) : $option;
    }

    /**
     * Record a created/known option in the cache — but only if this attribute's
     * map is already loaded, so a one-off addOption() can't leave a partial map
     * that would make a later ensureOptions() miss existing options.
     */
    private function rememberOption(string $attributeCode, string $label, string $id): void
    {
        if ($label !== '' && array_key_exists($attributeCode, $this->optionMaps)) {
            $this->optionMaps[$attributeCode][self::normalizeLabel($label)] = $id;
        }
    }

    /**
     * Single page of an attribute search.
     *
     * @return array<string,mixed> Magento envelope (items, search_criteria, total_count)
     */
    public function search(SearchCriteria $criteria): array
    {
        return (array) $this->http->get('V1/products/attributes', $criteria->toArray());
    }

    /**
     * Paginated attribute search returning every matching attribute (merged
     * `items`), working around Magento's page-size cap.
     *
     * @return list<array<string,mixed>>
     */
    public function searchAll(SearchCriteria $criteria, int $pageSize = self::MAX_PAGE_SIZE): array
    {
        return $this->paginateItems('V1/products/attributes', $criteria, $pageSize);
    }

    private static function normalizeLabel(string $label): string
    {
        return mb_strtolower(trim($label), 'UTF-8');
    }
}
