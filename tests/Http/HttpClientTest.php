<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Tests\Http;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Baldie81\Magento2SDK\Exception\ApiException;
use Baldie81\Magento2SDK\Exception\TransportException;
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
}
