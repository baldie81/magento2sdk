<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Tests\Resource;

use GuzzleHttp\Psr7\Response;
use Baldie81\Magento2SDK\Enum\OrderState;
use Baldie81\Magento2SDK\Support\SearchCriteria;
use Baldie81\Magento2SDK\Tests\MockClientTestCase;

final class OrdersTest extends MockClientTestCase
{
    public function testGetReturnsEntity(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode(['entity_id' => 7, 'state' => 'processing']))]);
        $order  = $client->orders()->get(7);

        self::assertSame('processing', $order['state']);
        self::assertSame('https://example.test/rest/V1/orders/7', (string) $this->lastRequest()->getUri());
    }

    public function testGetStatusResolvesKnownStateToEnum(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode('processing'))]);
        $status = $client->orders()->getStatus(7);

        self::assertSame(OrderState::Processing, $status);
        $request = $this->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://example.test/rest/V1/orders/7/statuses', (string) $request->getUri());
    }

    public function testGetStatusFallsBackToRawStringForCustomStatus(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode('awaiting_pickup'))]);

        self::assertSame('awaiting_pickup', $client->orders()->getStatus(7));
    }

    public function testCreateWrapsEntity(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode(['entity_id' => 7]))]);
        $client->orders()->create(['customer_email' => 'jane@example.com', 'state' => 'new']);

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://example.test/rest/V1/orders', (string) $request->getUri());
        $body = json_decode((string) $request->getBody(), true);
        self::assertSame('jane@example.com', $body['entity']['customer_email']);
    }

    public function testInvoiceHitsSingularPathAndCapturesByDefault(): void
    {
        $client = $this->mockClient([new Response(200, [], '42')]);
        $invoiceId = $client->orders()->invoice(7);

        self::assertSame(42, $invoiceId);
        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://example.test/rest/V1/order/7/invoice', (string) $request->getUri());
        $body = json_decode((string) $request->getBody(), true);
        self::assertTrue($body['capture']);
        self::assertFalse($body['notify']);
        self::assertArrayNotHasKey('items', $body);
    }

    public function testInvoicePartialWithItemsAndComment(): void
    {
        $client = $this->mockClient([new Response(200, [], '43')]);
        $client->orders()->invoice(7, [['order_item_id' => 11, 'qty' => 2]], capture: false, notify: true, comment: 'Partial');

        $body = json_decode((string) $this->lastRequest()->getBody(), true);
        self::assertFalse($body['capture']);
        self::assertTrue($body['notify']);
        self::assertSame([['order_item_id' => 11, 'qty' => 2]], $body['items']);
        self::assertTrue($body['appendComment']);
        self::assertSame('Partial', $body['comment']['comment']);
    }

    public function testShipHitsSingularPathWithTracks(): void
    {
        $client = $this->mockClient([new Response(200, [], '99')]);
        $shipmentId = $client->orders()->ship(
            7,
            [['order_item_id' => 11, 'qty' => 1]],
            [['track_number' => 'Z1', 'title' => 'UPS', 'carrier_code' => 'ups']],
        );

        self::assertSame(99, $shipmentId);
        $request = $this->lastRequest();
        self::assertSame('https://example.test/rest/V1/order/7/ship', (string) $request->getUri());
        $body = json_decode((string) $request->getBody(), true);
        self::assertSame([['order_item_id' => 11, 'qty' => 1]], $body['items']);
        self::assertSame('ups', $body['tracks'][0]['carrier_code']);
    }

    public function testCancelPostsToPluralPathAndReturnsBool(): void
    {
        $client = $this->mockClient([new Response(200, [], 'true')]);
        $cancelled = $client->orders()->cancel(7);

        self::assertTrue($cancelled);
        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://example.test/rest/V1/orders/7/cancel', (string) $request->getUri());
    }

    public function testSearchAllPaginatesAndMergesItems(): void
    {
        $client = $this->mockClient([
            new Response(200, [], json_encode(['items' => array_fill(0, 300, ['entity_id' => 1]), 'total_count' => 350])),
            new Response(200, [], json_encode(['items' => array_fill(0, 50, ['entity_id' => 2]), 'total_count' => 350])),
        ]);

        $items = $client->orders()->searchAll(SearchCriteria::create()->where('state', 'processing'));

        self::assertCount(350, $items);
        self::assertStringContainsString('rest/V1/orders?', (string) $this->history[0]['request']->getUri());
    }
}
