<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Support;

/**
 * Fluent builder for a customer address (the entries in a customer's `addresses`
 * array). Magento stores the region as a nested object alongside a top-level
 * `region_id`; set whichever parts you have and the builder assembles both.
 *
 *   Address::make()
 *       ->firstname('Jane')->lastname('Doe')
 *       ->street(['123 Oak Ave'])
 *       ->city('Austin')->region('Texas', regionId: 57)->postcode('78758')
 *       ->countryId('US')->telephone('512-555-1111')
 *       ->defaultBilling(true)->defaultShipping(true);
 */
final class Address implements Arrayable
{
    /** @var array<string,mixed> */
    private array $fields = [];

    private ?string $regionName = null;
    private ?string $regionCode = null;
    private ?int $regionId = null;

    /** @var array<string,mixed> */
    private array $customAttributes = [];

    public static function make(): self
    {
        return new self();
    }

    public function firstname(string $firstname): self
    {
        $this->fields['firstname'] = $firstname;

        return $this;
    }

    public function lastname(string $lastname): self
    {
        $this->fields['lastname'] = $lastname;

        return $this;
    }

    public function company(string $company): self
    {
        $this->fields['company'] = $company;

        return $this;
    }

    /**
     * @param list<string> $lines one entry per street line
     */
    public function street(array $lines): self
    {
        $this->fields['street'] = array_values($lines);

        return $this;
    }

    public function city(string $city): self
    {
        $this->fields['city'] = $city;

        return $this;
    }

    /**
     * Set the region. Supply the name and, where known, the Magento region id
     * (directory directory_country_region) and/or ISO region code; the id is
     * also mirrored to the top-level `region_id` Magento indexes on.
     */
    public function region(string $region, ?int $regionId = null, ?string $regionCode = null): self
    {
        $this->regionName = $region;
        if ($regionId !== null) {
            $this->regionId = $regionId;
        }
        if ($regionCode !== null) {
            $this->regionCode = $regionCode;
        }

        return $this;
    }

    public function regionId(int $regionId): self
    {
        $this->regionId = $regionId;

        return $this;
    }

    public function regionCode(string $regionCode): self
    {
        $this->regionCode = $regionCode;

        return $this;
    }

    public function postcode(string $postcode): self
    {
        $this->fields['postcode'] = $postcode;

        return $this;
    }

    /**
     * Two-letter ISO country code, e.g. "US", "GB".
     */
    public function countryId(string $countryId): self
    {
        $this->fields['country_id'] = $countryId;

        return $this;
    }

    public function telephone(string $telephone): self
    {
        $this->fields['telephone'] = $telephone;

        return $this;
    }

    public function vatId(string $vatId): self
    {
        $this->fields['vat_id'] = $vatId;

        return $this;
    }

    public function defaultBilling(bool $default = true): self
    {
        $this->fields['default_billing'] = $default;

        return $this;
    }

    public function defaultShipping(bool $default = true): self
    {
        $this->fields['default_shipping'] = $default;

        return $this;
    }

    public function customAttribute(string $code, mixed $value): self
    {
        $this->customAttributes[$code] = $value;

        return $this;
    }

    /**
     * Escape hatch for any address field not modelled above (e.g. `id` when
     * editing an existing address, `prefix`, `suffix`, `fax`).
     */
    public function set(string $field, mixed $value): self
    {
        $this->fields[$field] = $value;

        return $this;
    }

    public function toArray(): array
    {
        $address = $this->fields;

        $region = [];
        if ($this->regionName !== null) {
            $region['region'] = $this->regionName;
        }
        if ($this->regionCode !== null) {
            $region['region_code'] = $this->regionCode;
        }
        if ($this->regionId !== null) {
            $region['region_id']  = $this->regionId;
            $address['region_id'] = $this->regionId;
        }
        if ($region !== []) {
            $address['region'] = $region;
        }

        if ($this->customAttributes !== []) {
            $address['custom_attributes'] = [];
            foreach ($this->customAttributes as $code => $value) {
                $address['custom_attributes'][] = ['attribute_code' => $code, 'value' => $value];
            }
        }

        return $address;
    }
}
