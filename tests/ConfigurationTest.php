<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Tests;

use Baldie81\Magento2SDK\Configuration;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigurationTest extends TestCase
{
    public function testRestPrefixIsUnscopedWithoutAStoreCode(): void
    {
        self::assertSame('rest/', (new Configuration('https://shop.test', 't'))->restPrefix());
        self::assertSame('rest/', (new Configuration('https://shop.test', 't', storeCode: ' / '))->restPrefix());
    }

    public function testRestPrefixStripsSlashesAndWhitespaceFromTheStoreCode(): void
    {
        $config = new Configuration('https://shop.test', 't', storeCode: ' /fr/ ');

        self::assertSame('rest/fr/', $config->restPrefix());
    }

    public function testNormalizedBaseUrlEndsInASingleSlash(): void
    {
        self::assertSame('https://shop.test/', (new Configuration('https://shop.test//', 't'))->normalizedBaseUrl());
    }

    public function testBaseUrlAndTokenAreTrimmed(): void
    {
        $config = new Configuration("  https://shop.test/magento \n", " tok\n");

        self::assertSame('https://shop.test/magento/', $config->normalizedBaseUrl());
        self::assertSame('tok', $config->token);
    }

    public function testPlainHttpIsRefusedUnlessExplicitlyAllowed(): void
    {
        $config = new Configuration('http://localhost:8080', 't', allowInsecureHttp: true);
        self::assertSame('http://localhost:8080/', $config->normalizedBaseUrl());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('https://');
        new Configuration('http://shop.test', 't');
    }

    /**
     * @return iterable<string,array{0:array<string,mixed>}>
     */
    public static function invalidSettings(): iterable
    {
        yield 'empty url'          => [['baseUrl' => '  ']];
        yield 'no scheme'          => [['baseUrl' => 'shop.test']];
        yield 'other scheme'       => [['baseUrl' => 'ftp://shop.test']];
        yield 'query string'       => [['baseUrl' => 'https://shop.test/?a=1']];
        yield 'fragment'           => [['baseUrl' => 'https://shop.test/#x']];
        yield 'empty token'        => [['token' => " \n"]];
        yield 'token with newline' => [['token' => "tok\r\nX-Injected: 1"]];
        yield 'negative timeout'   => [['timeout' => -1.0]];
        yield 'negative connect'   => [['connectTimeout' => -1.0]];
        yield 'store traversal'    => [['storeCode' => '../../admin']];
        yield 'store with query'   => [['storeCode' => 'fr?x=1#']];
        yield 'store with slash'   => [['storeCode' => 'a/b']];
    }

    /**
     * @param array<string,mixed> $override
     */
    #[DataProvider('invalidSettings')]
    public function testMalformedSettingsAreRejected(array $override): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Configuration(...array_merge(['baseUrl' => 'https://shop.test', 'token' => 't'], $override));
    }

    public function testDebugOutputRedactsTheToken(): void
    {
        $config = new Configuration('https://shop.test', 'TOP-SECRET', defaultHeaders: [
            'X-Api-Key' => 'ALSO-SECRET',
            'X-Trace'   => 'visible',
        ]);

        $dump = print_r($config, true);

        self::assertStringNotContainsString('TOP-SECRET', $dump);
        self::assertStringNotContainsString('ALSO-SECRET', $dump);
        self::assertStringContainsString('visible', $dump);
    }
}
