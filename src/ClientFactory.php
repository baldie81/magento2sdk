<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK;

use GuzzleHttp\Client;

/**
 * Builds a configured Guzzle client from a {@see Configuration}.
 *
 * Kept separate from the SDK client so callers who want to customise the
 * transport (middleware, handlers, retries) can build their own Guzzle client
 * and hand it to {@see MagentoClient::fromGuzzle()} instead.
 */
final class ClientFactory
{
    public static function create(Configuration $config): Client
    {
        return new Client([
            'base_uri'        => $config->normalizedBaseUrl(),
            'timeout'         => $config->timeout,
            'connect_timeout' => $config->connectTimeout,
            'verify'          => $config->verifySsl,
            'http_errors'     => true,
            'headers'         => array_merge([
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $config->token,
            ], $config->defaultHeaders),
        ]);
    }
}
