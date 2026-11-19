<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Tests\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Baldie81\Magento2SDK\Exception\ApiException;
use Baldie81\Magento2SDK\Exception\TransportException;
use Baldie81\Magento2SDK\MagentoClient;
use Baldie81\Magento2SDK\Tests\MockClientTestCase;

final class HttpClientTest extends MockClientTestCase
{
    public function testConnectErrorBecomesTransportException(): void
    {
        $client = $this->mockClient([
            new ConnectException('Connection refused', new Request('GET', 'rest/V1/products/X')),
        ]);

        $this->expectException(TransportException::class);
        $client->products()->get('X');
    }

    public function testErrorResponseBecomesApiExceptionWithStatus(): void
    {
        $client = $this->mockClient([
            new Response(404, [], json_encode(['message' => 'Requested product does not exist'])),
        ]);

        try {
            $client->products()->get('missing');
            self::fail('Expected ApiException was not thrown.');
        } catch (ApiException $e) {
            self::assertSame(404, $e->statusCode);
            self::assertSame('Requested product does not exist', $e->getMessage());
        }
    }

    public function testNonJsonSuccessBodyThrowsInsteadOfDecodingToNothing(): void
    {
        $client = $this->mockClient([
            new Response(200, [], '<html><body>Service temporarily unavailable</body></html>'),
        ]);

        try {
            $client->products()->get('X');
            self::fail('Expected ApiException was not thrown.');
        } catch (ApiException $e) {
            self::assertSame(200, $e->statusCode);
            self::assertStringContainsString('non-JSON', $e->getMessage());
            self::assertStringContainsString('Service temporarily unavailable', (string) $e->rawBody);
        }
    }

    public function testEmptyBodyStillDecodesToNull(): void
    {
        $client = $this->mockClient([new Response(200, [], '')]);

        self::assertNull($client->http()->get('V1/anything'));
    }

    public function testErrorStatusThrowsEvenWhenTheGuzzleClientDisablesHttpErrors(): void
    {
        $guzzle = new Client([
            'handler'     => HandlerStack::create(new MockHandler([
                new Response(400, [], json_encode(['message' => 'Bad request'])),
            ])),
            'base_uri'    => 'https://example.test/',
            'http_errors' => false,
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Bad request');
        MagentoClient::fromGuzzle($guzzle)->products()->get('X');
    }

    public function testRequestErrorWithoutAResponseBecomesTransportException(): void
    {
        $client = $this->mockClient([
            new RequestException('cURL error 52: Empty reply from server', new Request('GET', 'rest/V1/products/X')),
        ]);

        $this->expectException(TransportException::class);
        $client->products()->get('X');
    }

    public function testRedirectIsAnErrorAndIsNeverFollowed(): void
    {
        // The mock client leaves Guzzle's redirect middleware on (its default).
        $client = $this->mockClient([
            new Response(302, ['Location' => 'https://example.test/rest/V1/products']),
            new Response(200, [], json_encode(['items' => []])),
        ]);

        try {
            $client->products()->create(['sku' => 'NEW']);
            self::fail('Expected ApiException was not thrown.');
        } catch (ApiException $e) {
            self::assertSame(302, $e->statusCode);
            self::assertStringContainsString('https://example.test/rest/V1/products', $e->getMessage());
            self::assertCount(1, $this->history);
            self::assertSame('POST', $this->lastRequest()->getMethod());
        }
    }

    public function testApiExceptionNamesTheRequestWithoutCarryingIt(): void
    {
        $client = $this->mockClient([new Response(400, [], json_encode(['message' => 'Nope']))]);

        try {
            $client->customers()->create(['email' => 'jane@example.com'], 'p4ssw0rd');
            self::fail('Expected ApiException was not thrown.');
        } catch (ApiException $e) {
            self::assertSame('POST', $e->method);
            self::assertSame('https://example.test/rest/V1/customers', $e->uri);
            // The Guzzle exception holds the request (Authorization header,
            // body); it must not travel with ours.
            self::assertNull($e->getPrevious());
            self::assertStringNotContainsString('p4ssw0rd', print_r($e, true));
        }
    }

    public function testTransportExceptionDoesNotCarryTheRequest(): void
    {
        $client = $this->mockClient([
            new ConnectException('Connection refused', new Request('GET', 'rest/V1/products/X')),
        ]);

        try {
            $client->products()->get('X');
            self::fail('Expected TransportException was not thrown.');
        } catch (TransportException $e) {
            self::assertSame('Connection refused', $e->getMessage());
            self::assertNull($e->getPrevious());
        }
    }

    public function testUnscopedDropsTheStoreSegment(): void
    {
        $client = $this->mockClient([new Response(200, [], '[]'), new Response(200, [], '[]')]);

        $client->forStore('fr')->http()->unscoped()->get('V1/products');
        self::assertSame('/rest/V1/products', $this->lastRequest()->getUri()->getPath());

        $http = $client->http();
        self::assertSame($http, $http->unscoped());
    }
}
