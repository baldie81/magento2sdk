<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK;

use InvalidArgumentException;

/**
 * Immutable connection settings for a Magento 2 store.
 *
 * Authentication is a bearer token (an Integration access token or an admin
 * token obtained out of band). TLS verification defaults to on; disable it only
 * for local/self-signed environments.
 */
final class Configuration
{
    /**
     * @param string                $baseUrl        Store base URL, e.g. "https://shop.example.com" (with or without a trailing slash).
     * @param string                $token          Bearer token sent as "Authorization: Bearer <token>".
     * @param float                 $timeout        Request timeout in seconds (0 = no limit).
     * @param float                 $connectTimeout Connection timeout in seconds.
     * @param bool                  $verifySsl      Verify the peer's TLS certificate.
     * @param string|null           $storeCode      Optional store-view code; scopes calls to "rest/<code>/V1/...".
     * @param array<string,string>  $defaultHeaders Extra headers merged into every request.
     */
    public function __construct(
        public readonly string $baseUrl,
        #[\SensitiveParameter]
        public readonly string $token,
        public readonly float $timeout = 30.0,
        public readonly float $connectTimeout = 10.0,
        public readonly bool $verifySsl = true,
        public readonly ?string $storeCode = null,
        public readonly array $defaultHeaders = [],
    ) {
        if (trim($baseUrl) === '') {
            throw new InvalidArgumentException('Configuration: baseUrl must not be empty.');
        }
        if (trim($token) === '') {
            throw new InvalidArgumentException('Configuration: token must not be empty.');
        }
    }

    /**
     * Base URL guaranteed to end in a single slash, suitable as a Guzzle base_uri.
     */
    public function normalizedBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/') . '/';
    }

    /**
     * The REST path prefix, store-scoped when a store code is set.
     */
    public function restPrefix(): string
    {
        $code = $this->storeCode !== null ? trim($this->storeCode, "/ \t\n\r\0\x0B") : '';

        return $code !== '' ? "rest/{$code}/" : 'rest/';
    }
}
