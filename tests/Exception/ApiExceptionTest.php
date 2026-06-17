<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Tests\Exception;

use GuzzleHttp\Psr7\Response;
use Baldie81\Magento2SDK\Exception\ApiException;
use PHPUnit\Framework\TestCase;

final class ApiExceptionTest extends TestCase
{
    public function testInterpolatesNumberedAndNamedParameters(): void
    {
        $response = new Response(400, [], json_encode([
            'message'    => 'The "%fieldName" value of %1 is invalid',
            'parameters' => ['fieldName' => 'sku', 'ABC'],
        ]));

        $exception = ApiException::fromResponse($response);

        self::assertSame(400, $exception->statusCode);
        self::assertSame('The "sku" value of ABC is invalid', $exception->getMessage());
        self::assertSame(['fieldName' => 'sku', 0 => 'ABC'], $exception->parameters);
    }

    public function testFallsBackToRawBodyWhenNoMessageField(): void
    {
        $response  = new Response(500, [], 'upstream exploded');
        $exception = ApiException::fromResponse($response);

        self::assertSame(500, $exception->statusCode);
        self::assertSame('upstream exploded', $exception->getMessage());
    }

    public function testInterpolatesTopLevelPlaceholderParameters(): void
    {
        $response = new Response(400, [], json_encode([
            'message' => 'Option %1 is not right',
            '%1'      => 'color',
        ]));

        $exception = ApiException::fromResponse($response);

        self::assertSame('Option color is not right', $exception->getMessage());
    }

    public function testNumberedPlaceholdersDoNotClobberEachOther(): void
    {
        $response = new Response(400, [], json_encode([
            'message' => 'Items %1 and %10 differ',
            '%1'      => 'A',
            '%10'     => 'B',
        ]));

        // Naive %1-first replacement would turn "%10" into "A0"; strtr must not.
        $exception = ApiException::fromResponse($response);
        self::assertSame('Items A and B differ', $exception->getMessage());
    }

    public function testConvertsNonUtf8BodyToUtf8(): void
    {
        $utf8  = json_encode(['message' => 'Σφάλμα προϊόντος'], JSON_UNESCAPED_UNICODE);
        $greek = mb_convert_encoding($utf8, 'ISO-8859-7', 'UTF-8'); // non-UTF-8 bytes on the wire

        $exception = ApiException::fromResponse(new Response(500, [], $greek));

        self::assertSame('Σφάλμα προϊόντος', $exception->getMessage());
    }
}
