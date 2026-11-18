<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Http;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
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
    public function post(string $path, array $body = []): mixed
    {
        return $this->request('POST', $path, ['json' => $body]);
    }

    /**
     * @param array<mixed> $body
     * @throws ApiException|TransportException
     */
    public function put(string $path, array $body = []): mixed
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
     * response). Used by the bulk resource to run batches concurrently.
     *
     * @param array<mixed> $body
     */
    public function postAsync(string $path, array $body = []): PromiseInterface
    {
        return $this->client->requestAsync('POST', $this->uri($path), ['json' => $body, 'http_errors' => true]);
    }

    /**
     * Decode a PSR-7 response body as JSON. Returns null for an empty body and
     * preserves scalars (some Magento endpoints return a bare string/int/bool).
     *
     * @throws ApiException when the body is not valid JSON (e.g. an HTML
     *                      maintenance or firewall page served with a 2xx status)
     */
    public function decode(ResponseInterface $response): mixed
    {
        $body = (string) $response->getBody();

        if (trim($body) === '') {
            return null;
        }

        try {
            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $status = $response->getStatusCode();

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
    private function request(string $method, string $path, array $options = []): mixed
    {
        // Forced on so a caller-supplied Guzzle client configured with
        // http_errors=false can't turn 4xx/5xx bodies into "successful" results.
        $options['http_errors'] = true;

        try {
            $response = $this->client->request($method, $this->uri($path), $options);
        } catch (ConnectException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        } catch (RequestException $e) {
            if (!$e->hasResponse()) {
                throw new TransportException($e->getMessage(), 0, $e);
            }

            throw ApiException::fromRequestException($e);
        } catch (GuzzleException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        return $this->decode($response);
    }

    private function uri(string $path): string
    {
        return $this->restPrefix . ltrim($path, '/');
    }
}
