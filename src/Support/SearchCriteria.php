<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Support;

/**
 * Fluent builder for Magento's verbose `searchCriteria[...]` query parameters.
 *
 * Magento ANDs filter groups together and ORs the filters within a group:
 *   - {@see where()} adds a new group with a single filter (AND semantics).
 *   - {@see whereAny()} adds one group with several filters (OR semantics).
 *
 * Example:
 *   SearchCriteria::create()
 *       ->where('status', 1)
 *       ->where('price', 100, 'gt')
 *       ->sort('created_at', 'DESC')
 *       ->pageSize(50)
 *       ->fields('items[sku,price]');
 */
final class SearchCriteria
{
    /** @var list<list<array{field:string,value:mixed,conditionType:string}>> */
    private array $filterGroups = [];

    /** @var list<array{field:string,direction:string}> */
    private array $sortOrders = [];

    private ?int $pageSize = null;
    private ?int $currentPage = null;

    /** @var list<string> */
    private array $fields = [];

    public static function create(): self
    {
        return new self();
    }

    /**
     * Add an AND filter (its own group). Pass an array value with the "in"/"nin"
     * condition type to match a set; it is joined with commas as Magento expects.
     */
    public function where(string $field, mixed $value, string $conditionType = 'eq'): self
    {
        $this->filterGroups[] = [[
            'field'         => $field,
            'value'         => $value,
            'conditionType' => $conditionType,
        ]];

        return $this;
    }

    /**
     * Add a single group of OR'd filters.
     *
     * @param list<array{0:string,1:mixed,2?:string}> $filters [field, value, conditionType?] tuples
     */
    public function whereAny(array $filters): self
    {
        $group = [];
        foreach ($filters as $filter) {
            $group[] = [
                'field'         => $filter[0],
                'value'         => $filter[1],
                'conditionType' => $filter[2] ?? 'eq',
            ];
        }
        if ($group !== []) {
            $this->filterGroups[] = $group;
        }

        return $this;
    }

    public function sort(string $field, string $direction = 'ASC'): self
    {
        $this->sortOrders[] = [
            'field'     => $field,
            'direction' => strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC',
        ];

        return $this;
    }

    public function pageSize(int $pageSize): self
    {
        $this->pageSize = $pageSize;

        return $this;
    }

    public function currentPage(int $currentPage): self
    {
        $this->currentPage = $currentPage;

        return $this;
    }

    /**
     * Limit the fields Magento returns (the top-level `fields` query param).
     */
    public function fields(string ...$fields): self
    {
        foreach ($fields as $field) {
            $this->fields[] = $field;
        }

        return $this;
    }

    /**
     * Flatten to the query-parameter array Guzzle will serialise.
     *
     * @return array<string,scalar>
     */
    public function toArray(): array
    {
        $query = [];

        foreach ($this->filterGroups as $g => $group) {
            foreach ($group as $f => $filter) {
                $base = "searchCriteria[filterGroups][{$g}][filters][{$f}]";
                $query["{$base}[field]"]         = $filter['field'];
                $query["{$base}[value]"]         = self::stringifyValue($filter['value']);
                $query["{$base}[conditionType]"] = $filter['conditionType'];
            }
        }

        foreach ($this->sortOrders as $s => $sort) {
            $query["searchCriteria[sortOrders][{$s}][field]"]     = $sort['field'];
            $query["searchCriteria[sortOrders][{$s}][direction]"] = $sort['direction'];
        }

        if ($this->pageSize !== null) {
            $query['searchCriteria[pageSize]'] = $this->pageSize;
        }
        if ($this->currentPage !== null) {
            $query['searchCriteria[currentPage]'] = $this->currentPage;
        }
        if ($this->fields !== []) {
            $query['fields'] = implode(',', $this->fields);
        }

        return $query;
    }

    private static function stringifyValue(mixed $value): string
    {
        if (is_array($value)) {
            return implode(',', array_map(static fn ($v) => (string) $v, $value));
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
