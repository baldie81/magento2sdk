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
            // Never followed: a redirect would turn a POST/PUT into a GET, or
            // replay a request body to another scheme/host.
            'allow_redirects' => false,
            'headers'         => self::headers($config),
        ]);
    }

    /**
     * The SDK's headers with the caller's merged over them. Header names are
     * case-insensitive, so "authorization" replaces "Authorization" rather
     * than being sent alongside it.
     *
     * @return array<string,string>
     */
    private static function headers(Configuration $config): array
    {
        $headers = [
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $config->token,
        ];

        foreach ($config->defaultHeaders as $name => $value) {
            foreach (array_keys($headers) as $existing) {
                if (strcasecmp($existing, (string) $name) === 0) {
                    unset($headers[$existing]);
                }
            }
            $headers[(string) $name] = $value;
        }

        return $headers;
    }
}
