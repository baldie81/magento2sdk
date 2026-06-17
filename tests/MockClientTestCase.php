<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Baldie81\Magento2SDK\MagentoClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * Base case that wires a {@see MagentoClient} onto a Guzzle MockHandler and
 * records the outgoing requests for assertions.
 */
abstract class MockClientTestCase extends TestCase
{
    /** @var list<array{request:RequestInterface,response:mixed,error:mixed,options:array}> */
    protected array $history = [];

    /**
     * @param list<mixed> $responses queued Response/Exception objects
     */
    protected function mockClient(array $responses): MagentoClient
    {
        $this->history = [];

        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        $guzzle = new Client([
            'handler'     => $stack,
            'base_uri'    => 'https://example.test/',
            'http_errors' => true,
        ]);

        return MagentoClient::fromGuzzle($guzzle, 'rest/');
    }

    protected function lastRequest(): RequestInterface
    {
        return $this->history[array_key_last($this->history)]['request'];
    }
}
