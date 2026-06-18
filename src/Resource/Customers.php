<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Resource;

use Baldie81\Magento2SDK\Support\Customer;
use Baldie81\Magento2SDK\Support\SearchCriteria;

/**
 * Customers endpoint (`rest/V1/customers`).
 *
 * Addresses are not a separate write endpoint in core Magento: they are saved
 * inline as part of the customer ({@see Customer::address()}). Only deletion has
 * a dedicated route ({@see deleteAddress()}).
 */
final class Customers extends AbstractResource
{
    /**
     * @return array<string,mixed> the customer entity (addresses included)
     */
    public function get(int $customerId): array
    {
        return (array) $this->http->get("V1/customers/{$customerId}");
    }

    /**
     * Run a customer search for a single page (note the dedicated
     * `customers/search` path). Returns the full Magento envelope. Use
     * {@see searchAll()} for every page.
     *
     * @return array<string,mixed>
     */
    public function search(SearchCriteria $criteria): array
    {
        return (array) $this->http->get('V1/customers/search', $criteria->toArray());
    }

    /**
     * Like {@see search()} but paginates and returns every matching customer
     * (merged `items`), working around Magento's page-size cap.
     *
     * @return list<array<string,mixed>>
     */
    public function searchAll(SearchCriteria $criteria, int $pageSize = self::MAX_PAGE_SIZE): array
    {
        return $this->paginateItems('V1/customers/search', $criteria, $pageSize);
    }

    /**
     * Create a customer account. Accepts a {@see Customer} builder or a raw
     * array (the inner customer body, without the "customer" wrapper). Pass
     * `$password` to set the account password; omit it to have Magento email a
     * "set your password" link instead. Returns the created customer entity.
     *
     * @param Customer|array<string,mixed> $customer
     * @return array<string,mixed>
     */
    public function create(Customer|array $customer, ?string $password = null): array
    {
        $body = ['customer' => $this->payload($customer)];
        if ($password !== null) {
            $body['password'] = $password;
        }

        return (array) $this->http->post('V1/customers', $body);
    }

    /**
     * Update a customer through the repository. Magento replaces the customer's
     * address book with the `addresses` you send, so pass the full set you want
     * to keep (each existing address carrying its `id`). Returns the saved
     * customer entity.
     *
     * @param Customer|array<string,mixed> $customer
     * @return array<string,mixed>
     */
    public function update(int $customerId, Customer|array $customer): array
    {
        return (array) $this->http->put("V1/customers/{$customerId}", ['customer' => $this->payload($customer)]);
    }

    public function delete(int $customerId): bool
    {
        return (bool) $this->http->delete("V1/customers/{$customerId}");
    }

    /**
     * Delete a single customer address by its address id.
     */
    public function deleteAddress(int $addressId): bool
    {
        return (bool) $this->http->delete("V1/addresses/{$addressId}");
    }
}
