<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Tests;

use Baldie81\Magento2SDK\ClientFactory;
use Baldie81\Magento2SDK\Configuration;
use PHPUnit\Framework\TestCase;

final class ClientFactoryTest extends TestCase
{
    public function testSendsTheBearerTokenAndNeverFollowsRedirects(): void
    {
        $guzzle = ClientFactory::create(new Configuration('https://shop.test', 'tok'));

        self::assertSame('Bearer tok', $guzzle->getConfig('headers')['Authorization']);
        self::assertFalse($guzzle->getConfig('allow_redirects'));
        self::assertTrue($guzzle->getConfig('verify'));
    }

    public function testDefaultHeadersOverrideCaseInsensitively(): void
    {
        $guzzle = ClientFactory::create(new Configuration('https://shop.test', 'tok', defaultHeaders: [
            'authorization' => 'Bearer other',
            'X-Extra'       => '1',
        ]));

        $headers = $guzzle->getConfig('headers');

        self::assertArrayNotHasKey('Authorization', $headers);
        self::assertSame('Bearer other', $headers['authorization']);
        self::assertSame('1', $headers['X-Extra']);
        self::assertSame('application/json', $headers['Accept']);
    }
}
