<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK;

use InvalidArgumentException;

/**
 * Immutable connection settings for a Magento 2 store.
 *
 * Authentication is a bearer token (an Integration access token or an admin
 * token obtained out of band). The base URL must be `https://` — the token
 * travels in every request — unless `allowInsecureHttp` is set, which is meant
 * for local development only. TLS verification defaults to on; disable it only
 * for local/self-signed environments.
 */
final class Configuration
{
    public readonly string $baseUrl;
    public readonly string $token;
    public readonly ?string $storeCode;

    /**
     * @param string                $baseUrl           Store base URL, e.g. "https://shop.example.com" (with or without a trailing slash).
     * @param string                $token             Bearer token sent as "Authorization: Bearer <token>".
     * @param float                 $timeout           Request timeout in seconds (0 = no limit).
     * @param float                 $connectTimeout    Connection timeout in seconds (0 = no limit).
     * @param bool                  $verifySsl         Verify the peer's TLS certificate.
     * @param string|null           $storeCode         Optional store-view code; scopes calls to "rest/<code>/V1/...".
     * @param array<string,string>  $defaultHeaders    Extra headers merged into every request (names are case-insensitive).
     * @param bool                  $allowInsecureHttp Accept an "http://" base URL. The token is then sent in cleartext.
     *
     * @throws InvalidArgumentException when a setting is malformed
     */
    public function __construct(
        string $baseUrl,
        #[\SensitiveParameter]
        string $token,
        public readonly float $timeout = 30.0,
        public readonly float $connectTimeout = 10.0,
        public readonly bool $verifySsl = true,
        ?string $storeCode = null,
        public readonly array $defaultHeaders = [],
        public readonly bool $allowInsecureHttp = false,
    ) {
        $this->baseUrl   = self::validateBaseUrl($baseUrl, $allowInsecureHttp);
        $this->token     = self::validateToken($token);
        $this->storeCode = $storeCode !== null ? self::normalizeStoreCode($storeCode) : null;

        if ($timeout < 0) {
            throw new InvalidArgumentException('Configuration: timeout must not be negative.');
        }
        if ($connectTimeout < 0) {
            throw new InvalidArgumentException('Configuration: connectTimeout must not be negative.');
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
        return $this->storeCode !== null && $this->storeCode !== '' ? "rest/{$this->storeCode}/" : 'rest/';
    }

    /**
     * Trim a store-view code and check it is one Magento could have issued
     * (a letter followed by letters, digits or underscores), so it can never
     * add path segments, a query string or a fragment to the request URL.
     * An empty code is returned as '' (the default, unscoped view).
     *
     * @throws InvalidArgumentException
     */
    public static function normalizeStoreCode(string $storeCode): string
    {
        $code = trim($storeCode, "/ \t\n\r\0\x0B");

        if ($code !== '' && preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $code) !== 1) {
            throw new InvalidArgumentException(
                'Store code must start with a letter and contain only letters, digits and underscores.',
            );
        }

        return $code;
    }

    /**
     * Keep the token (and any credential-bearing header) out of var_dump(),
     * print_r() and debugger output.
     *
     * @return array<string,mixed>
     */
    public function __debugInfo(): array
    {
        $headers = [];
        foreach ($this->defaultHeaders as $name => $value) {
            $headers[$name] = preg_match('/auth|cookie|token|key|secret/i', (string) $name) === 1 ? '[redacted]' : $value;
        }

        return [
            'baseUrl'           => $this->baseUrl,
            'token'             => '[redacted]',
            'timeout'           => $this->timeout,
            'connectTimeout'    => $this->connectTimeout,
            'verifySsl'         => $this->verifySsl,
            'storeCode'         => $this->storeCode,
            'defaultHeaders'    => $headers,
            'allowInsecureHttp' => $this->allowInsecureHttp,
        ];
    }

    private static function validateBaseUrl(string $baseUrl, bool $allowInsecureHttp): string
    {
        $baseUrl = trim($baseUrl);
        if ($baseUrl === '') {
            throw new InvalidArgumentException('Configuration: baseUrl must not be empty.');
        }

        $parts  = parse_url($baseUrl);
        $scheme = is_array($parts) ? strtolower($parts['scheme'] ?? '') : '';

        if (!is_array($parts) || ($parts['host'] ?? '') === '' || !in_array($scheme, ['https', 'http'], true)) {
            throw new InvalidArgumentException(
                'Configuration: baseUrl must be an absolute URL such as "https://shop.example.com".',
            );
        }
        if ($scheme === 'http' && !$allowInsecureHttp) {
            throw new InvalidArgumentException(
                'Configuration: baseUrl must use https:// — the bearer token is sent with every request.'
                . ' Pass allowInsecureHttp: true to use http:// in local development.',
            );
        }
        if (isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('Configuration: baseUrl must not carry a query string or fragment.');
        }

        return $baseUrl;
    }

    private static function validateToken(#[\SensitiveParameter] string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            throw new InvalidArgumentException('Configuration: token must not be empty.');
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $token) === 1) {
            throw new InvalidArgumentException('Configuration: token must not contain control characters.');
        }

        return $token;
    }
}
