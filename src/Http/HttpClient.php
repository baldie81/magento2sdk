<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Http;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use Baldie81\Magento2SDK\Exception\ApiException;
use Baldie81\Magento2SDK\Exception\TransportException;
use JsonException;
use Psr\Http\Message\ResponseInterface;

/**
 * Thin transport layer over a PSR-compatible Guzzle client.
 *
 * Resources speak in REST paths relative to the prefix (e.g. "V1/products");
 * this class prepends the prefix ("rest/" or "rest/<store>/"), JSON-encodes
 * request bodies, decodes responses, and normalises Guzzle errors into the
 * SDK's own exception hierarchy so callers never have to catch Guzzle types.
 *
 * Redirects are never followed and a 3xx answer is an error: following one
 * would turn a write into a GET, or replay a request body (customer data,
 * passwords) to another scheme or host.
 *
 * The Guzzle exception is deliberately not chained as `previous`: it holds the
 * request, and with it the Authorization header and the request body, which
 * would leak into logs and error reporters that serialise the chain. Request
 * bodies are likewise marked sensitive so they stay out of stack traces.
 */
final class HttpClient
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly string $restPrefix = 'rest/',
    ) {}

    /**
     * A clone that reuses the same underlying transport but with a different REST
     * prefix (e.g. store-scoped "rest/<store>/").
     */
    public function withRestPrefix(string $restPrefix): self
    {
        return new self($this->client, $restPrefix);
    }

    /**
     * A clone with any store-view segment dropped from the REST prefix
     * ("rest/<store>/" becomes "rest/"); returns this instance when the prefix
     * is already unscoped.
     */
    public function unscoped(): self
    {
        $prefix = preg_replace('~(^|/)rest/[^/]+/$~', '$1rest/', $this->restPrefix) ?? $this->restPrefix;

        return $prefix === $this->restPrefix ? $this : new self($this->client, $prefix);
    }

    /**
     * @param array<string,mixed> $query
     * @throws ApiException|TransportException
     */
    public function get(string $path, array $query = []): mixed
    {
        return $this->request('GET', $path, $query !== [] ? ['query' => $query] : []);
    }

    /**
     * @param array<mixed> $body
     * @throws ApiException|TransportException
     */
    public function post(string $path, #[\SensitiveParameter] array $body = []): mixed
    {
        return $this->request('POST', $path, ['json' => $body]);
    }

    /**
     * @param array<mixed> $body
     * @throws ApiException|TransportException
     */
    public function put(string $path, #[\SensitiveParameter] array $body = []): mixed
    {
        return $this->request('PUT', $path, ['json' => $body]);
    }

    /**
     * @throws ApiException|TransportException
     */
    public function delete(string $path): mixed
    {
        return $this->request('DELETE', $path);
    }

    /**
     * Fire an async POST and return the raw promise (resolves to a PSR-7
     * response). Used by the bulk resource to run batches concurrently. Pass
     * the settled response through {@see decode()}, which also rejects
     * redirects.
     *
     * @param array<mixed> $body
     */
    public function postAsync(string $path, #[\SensitiveParameter] array $body = []): PromiseInterface
    {
        return $this->client->requestAsync('POST', $this->uri($path), [
            'json'            => $body,
            'http_errors'     => true,
            'allow_redirects' => false,
        ]);
    }

    /**
     * Decode a PSR-7 response body as JSON. Returns null for an empty body and
     * preserves scalars (some Magento endpoints return a bare string/int/bool).
     *
     * @throws ApiException when the response is a redirect, or the body is not
     *                      valid JSON (e.g. an HTML maintenance or firewall
     *                      page served with a 2xx status)
     */
    public function decode(ResponseInterface $response): mixed
    {
        $status = $response->getStatusCode();
        $body   = (string) $response->getBody();

        if ($status >= 300 && $status < 400) {
            $location = $response->getHeaderLine('Location');

            throw new ApiException(
                "Magento answered with a redirect (HTTP {$status}"
                . ($location !== '' ? " to {$location}" : '')
                . '). Redirects are not followed; point the base URL at the final address'
                . ' (scheme, host and path) of the store.',
                $status,
                $body,
            );
        }

        if (trim($body) === '') {
            return null;
        }

        try {
            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ApiException(
                "Magento returned a non-JSON response (HTTP {$status}): " . mb_substr(trim($body), 0, 200),
                $status,
                $body,
                [],
                $e,
            );
        }
    }

    /**
     * @param array<string,mixed> $options
     * @throws ApiException|TransportException
     */
    private function request(string $method, string $path, #[\SensitiveParameter] array $options = []): mixed
    {
        // Forced so a caller-supplied Guzzle client can't turn 4xx/5xx bodies
        // into "successful" results (http_errors=false) or follow redirects.
        $options['http_errors']     = true;
        $options['allow_redirects'] = false;

        try {
            $response = $this->client->request($method, $this->uri($path), $options);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                throw ApiException::fromRequestException($e);
            }

            throw new TransportException($e->getMessage());
        } catch (GuzzleException $e) {
            throw new TransportException($e->getMessage());
        }

        return $this->decode($response);
    }

    private function uri(string $path): string
    {
        return $this->restPrefix . ltrim($path, '/');
    }
}
