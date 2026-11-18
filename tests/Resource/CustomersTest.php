<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Tests\Resource;

use GuzzleHttp\Psr7\Response;
use Baldie81\Magento2SDK\Support\Address;
use Baldie81\Magento2SDK\Support\Customer;
use Baldie81\Magento2SDK\Support\SearchCriteria;
use Baldie81\Magento2SDK\Tests\MockClientTestCase;

final class CustomersTest extends MockClientTestCase
{
    public function testGetReturnsEntity(): void
    {
        $client   = $this->mockClient([new Response(200, [], json_encode(['id' => 5, 'email' => 'jane@example.com']))]);
        $customer = $client->customers()->get(5);

        self::assertSame('jane@example.com', $customer['email']);
        self::assertSame('https://example.test/rest/V1/customers/5', (string) $this->lastRequest()->getUri());
    }

    public function testCreateWrapsCustomerAndPassword(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode(['id' => 5]))]);

        $client->customers()->create(
            Customer::make('jane@example.com')->firstname('Jane')->lastname('Doe')->websiteId(1),
            password: 's3cret!',
        );

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://example.test/rest/V1/customers', (string) $request->getUri());
        $body = json_decode((string) $request->getBody(), true);
        self::assertSame('jane@example.com', $body['customer']['email']);
        self::assertSame(1, $body['customer']['website_id']);
        self::assertSame('s3cret!', $body['password']);
    }

    public function testCreateOmitsPasswordWhenNotGiven(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode(['id' => 5]))]);
        $client->customers()->create(['email' => 'jane@example.com']);

        $body = json_decode((string) $this->lastRequest()->getBody(), true);
        self::assertArrayNotHasKey('password', $body);
    }

    public function testUpdatePutsWrappedCustomerWithAddress(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode(['id' => 5]))]);

        $client->customers()->update(5, Customer::make('jane@example.com')->address(
            Address::make()->street(['1 A St'])->city('Austin')->countryId('US')->defaultBilling(),
        ));

        $request = $this->lastRequest();
        self::assertSame('PUT', $request->getMethod());
        self::assertSame('https://example.test/rest/V1/customers/5', (string) $request->getUri());
        $body = json_decode((string) $request->getBody(), true);
        self::assertSame(['1 A St'], $body['customer']['addresses'][0]['street']);
        self::assertTrue($body['customer']['addresses'][0]['default_billing']);
        self::assertSame(5, $body['customer']['id']); // added from the URL id
    }

    public function testUpdateKeepsAnExplicitIdInThePayload(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode(['id' => 5]))]);

        $client->customers()->update(5, ['id' => 5, 'email' => 'jane@example.com']);

        $body = json_decode((string) $this->lastRequest()->getBody(), true);
        self::assertSame(['id' => 5, 'email' => 'jane@example.com'], $body['customer']);
    }

    public function testSearchUsesDedicatedSearchPath(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode(['items' => [], 'total_count' => 0]))]);
        $client->customers()->search(SearchCriteria::create()->where('email', 'jane@example.com'));

        self::assertStringContainsString('rest/V1/customers/search?', (string) $this->lastRequest()->getUri());
    }

    public function testDeleteAddressHitsAddressesPath(): void
    {
        $client = $this->mockClient([new Response(200, [], 'true')]);
        $deleted = $client->customers()->deleteAddress(12);

        self::assertTrue($deleted);
        $request = $this->lastRequest();
        self::assertSame('DELETE', $request->getMethod());
        self::assertSame('https://example.test/rest/V1/addresses/12', (string) $request->getUri());
    }
}
