<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Exception;

use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * An error response from Magento (HTTP 4xx/5xx).
 *
 * Magento error bodies carry a `message` with "%1"/"%name" placeholders, and the
 * fill-in values arrive in more than one shape across versions/endpoints:
 *   - nested under `parameters` as a numbered list (%1, %2…) or a named map (%name)
 *   - as top-level sibling keys that are themselves the placeholder, e.g.
 *     {"message": "Option %1 is not right", "%1": "color"}
 * This exception interpolates whichever shape is present so {@see getMessage()}
 * reads naturally. The body is also normalised to UTF-8 first, since a store
 * whose default language isn't English can return non-UTF-8 bytes that would
 * otherwise make json_decode() fail.
 */
final class ApiException extends MagentoException
{
    /**
     * @param array<string|int,mixed> $parameters
     */
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly ?string $rawBody = null,
        public readonly array $parameters = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public static function fromResponse(ResponseInterface $response, ?Throwable $previous = null): self
    {
        $status  = $response->getStatusCode();
        $body    = self::toUtf8((string) $response->getBody());
        $decoded = json_decode($body, true);

        $parameters = is_array($decoded) && isset($decoded['parameters']) && is_array($decoded['parameters'])
            ? $decoded['parameters']
            : [];

        if (is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])) {
            $message = self::interpolate($decoded['message'], self::collectReplacements($decoded));

            // Validation failures carry the individual problems in a nested
            // `errors` list; append them so the message says what was wrong.
            $details = [];
            foreach (is_array($decoded['errors'] ?? null) ? $decoded['errors'] : [] as $error) {
                if (!is_array($error) || !is_string($error['message'] ?? null)) {
                    continue;
                }
                $detail = self::interpolate($error['message'], self::collectReplacements($error));
                if ($detail !== '' && $detail !== $message && !in_array($detail, $details, true)) {
                    $details[] = $detail;
                }
            }
            if ($details !== []) {
                $message = trim($message . ' ' . implode(' ', $details));
            }
        } elseif ($body !== '') {
            $message = mb_substr($body, 0, 500);
        } else {
            $message = "Magento returned HTTP {$status}.";
        }

        return new self($message, $status, $body, $parameters, $previous);
    }

    public static function fromRequestException(RequestException $e): self
    {
        $response = $e->getResponse();
        if ($response !== null) {
            return self::fromResponse($response, $e);
        }

        return new self($e->getMessage(), 0, null, [], $e);
    }

    /**
     * Best-effort human-readable description of any throwable, used where an
     * exception can't be rethrown (e.g. settled async rejection reasons).
     */
    public static function describe(?Throwable $e): string
    {
        if ($e instanceof RequestException) {
            return self::fromRequestException($e)->getMessage();
        }

        return $e !== null ? $e->getMessage() : 'unknown error';
    }

    /**
     * Collect the {placeholder => value} replacement map from both shapes:
     * nested `parameters` (numbered → %1.., or named → %name) and any top-level
     * sibling key that is itself a placeholder (e.g. "%1").
     *
     * @param array<string|int,mixed> $decoded
     * @return array<string,string>
     */
    private static function collectReplacements(array $decoded): array
    {
        $replacements = [];

        $parameters = $decoded['parameters'] ?? null;
        if (is_array($parameters)) {
            foreach ($parameters as $key => $value) {
                if (is_scalar($value)) {
                    $placeholder = is_int($key) ? '%' . ($key + 1) : '%' . $key;
                    $replacements[$placeholder] = (string) $value;
                }
            }
        }

        foreach ($decoded as $key => $value) {
            if (is_string($key) && $key !== 'message' && $key !== 'parameters'
                && is_scalar($value) && str_starts_with($key, '%')) {
                $replacements[$key] = (string) $value;
            }
        }

        return $replacements;
    }

    /**
     * @param array<string,string> $replacements
     */
    private static function interpolate(string $message, array $replacements): string
    {
        // strtr replaces longest keys first and never re-substitutes, so "%1"
        // can't clobber "%10".
        return $replacements === [] ? trim($message) : trim(strtr($message, $replacements));
    }

    /**
     * Ensure the body is UTF-8 so json_decode() can parse it even when the
     * store's default language emits another encoding (best-effort detection;
     * falls back to ISO-8859-1, which maps every byte rather than failing).
     */
    private static function toUtf8(string $body): string
    {
        if ($body === '' || mb_check_encoding($body, 'UTF-8')) {
            return $body;
        }

        $detected = mb_detect_encoding(
            $body,
            ['UTF-8', 'ISO-8859-7', 'ISO-8859-1', 'Windows-1252'],
            true,
        );

        $converted = mb_convert_encoding($body, 'UTF-8', $detected !== false ? $detected : 'ISO-8859-1');

        return $converted !== false ? $converted : $body;
    }
}
