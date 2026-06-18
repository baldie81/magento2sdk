<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Support;

/**
 * Fluent builder for a customer payload (the inner "customer" body — the
 * Customers resource adds the wrapper and, for create, the optional password).
 * Unset fields are omitted, so the same builder serves create and partial
 * update. Addresses are carried inline, the way Magento persists them.
 *
 *   Customer::make('jane@example.com')
 *       ->firstname('Jane')->lastname('Doe')
 *       ->websiteId(1)->groupId(1)
 *       ->address(Address::make()->street(['1 A St'])->city('Austin')->defaultBilling());
 */
final class Customer implements Arrayable
{
    /** @var array<string,mixed> */
    private array $fields = [];

    /** @var list<array<string,mixed>> */
    private array $addresses = [];

    /** @var array<string,mixed> */
    private array $customAttributes = [];

    /** @var array<string,mixed> */
    private array $extensionAttributes = [];

    public static function make(string $email): self
    {
        $customer = new self();
        $customer->fields['email'] = $email;

        return $customer;
    }

    public function email(string $email): self
    {
        $this->fields['email'] = $email;

        return $this;
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

    public function middlename(string $middlename): self
    {
        $this->fields['middlename'] = $middlename;

        return $this;
    }

    public function prefix(string $prefix): self
    {
        $this->fields['prefix'] = $prefix;

        return $this;
    }

    public function suffix(string $suffix): self
    {
        $this->fields['suffix'] = $suffix;

        return $this;
    }

    /**
     * Magento gender source: 1 = Male, 2 = Female, 3 = Not Specified.
     */
    public function gender(int $gender): self
    {
        $this->fields['gender'] = $gender;

        return $this;
    }

    /**
     * Date of birth as `YYYY-MM-DD`.
     */
    public function dob(string $dob): self
    {
        $this->fields['dob'] = $dob;

        return $this;
    }

    public function taxvat(string $taxvat): self
    {
        $this->fields['taxvat'] = $taxvat;

        return $this;
    }

    public function groupId(int $groupId): self
    {
        $this->fields['group_id'] = $groupId;

        return $this;
    }

    public function storeId(int $storeId): self
    {
        $this->fields['store_id'] = $storeId;

        return $this;
    }

    public function websiteId(int $websiteId): self
    {
        $this->fields['website_id'] = $websiteId;

        return $this;
    }

    /**
     * Append an address. Accepts an {@see Address} builder or a raw array.
     *
     * @param Address|array<string,mixed> $address
     */
    public function address(Address|array $address): self
    {
        $this->addresses[] = $address instanceof Arrayable ? $address->toArray() : $address;

        return $this;
    }

    /**
     * @param list<Address|array<string,mixed>> $addresses
     */
    public function addresses(array $addresses): self
    {
        foreach ($addresses as $address) {
            $this->address($address);
        }

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
     * Escape hatch for any top-level customer field not modelled above (e.g.
     * `id` when updating, `disable_auto_group_change`).
     */
    public function set(string $field, mixed $value): self
    {
        $this->fields[$field] = $value;

        return $this;
    }

    public function toArray(): array
    {
        $customer = $this->fields;

        if ($this->addresses !== []) {
            $customer['addresses'] = $this->addresses;
        }

        if ($this->extensionAttributes !== []) {
            $customer['extension_attributes'] = $this->extensionAttributes;
        }

        if ($this->customAttributes !== []) {
            $customer['custom_attributes'] = [];
            foreach ($this->customAttributes as $code => $value) {
                $customer['custom_attributes'][] = ['attribute_code' => $code, 'value' => $value];
            }
        }

        return $customer;
    }
}
