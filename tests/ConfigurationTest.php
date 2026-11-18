<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Tests;

use Baldie81\Magento2SDK\Configuration;
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
}
