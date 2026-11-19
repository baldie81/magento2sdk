<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Tests\Resource;

use GuzzleHttp\Psr7\Response;
use Baldie81\Magento2SDK\Enum\OrderState;
use Baldie81\Magento2SDK\Exception\UnexpectedResponseException;
use Baldie81\Magento2SDK\Support\OrderStatus;
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

    public function testGetStatusReturnsOrderStatusBridgingToState(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode('processing'))]);
        $status = $client->orders()->getStatus(7);

        self::assertInstanceOf(OrderStatus::class, $status);
        self::assertSame('processing', $status->code());
        self::assertSame(OrderState::Processing, $status->state());
        $request = $this->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://example.test/rest/V1/orders/7/statuses', (string) $request->getUri());
    }

    public function testGetStatusWrapsCustomStatusWithNoState(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode('awaiting_pickup'))]);
        $status = $client->orders()->getStatus(7);

        self::assertSame('awaiting_pickup', $status->code());
        self::assertNull($status->state());
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

    public function testRefundHitsSingularPathWithItemsAndArguments(): void
    {
        $client = $this->mockClient([new Response(200, [], '55')]);
        $creditMemoId = $client->orders()->refund(
            7,
            [['order_item_id' => 11, 'qty' => 2]],
            notify: true,
            comment: 'Damaged',
            arguments: ['shipping_amount' => 0, 'adjustment_negative' => 1.5],
        );

        self::assertSame(55, $creditMemoId);
        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://example.test/rest/V1/order/7/refund', (string) $request->getUri());
        $body = json_decode((string) $request->getBody(), true);
        self::assertSame([['order_item_id' => 11, 'qty' => 2]], $body['items']);
        self::assertTrue($body['notify']);
        self::assertTrue($body['appendComment']);
        self::assertSame('Damaged', $body['comment']['comment']);
        self::assertSame(1.5, $body['arguments']['adjustment_negative']);
    }

    public function testRefundFullOrderOmitsItemsAndArguments(): void
    {
        $client = $this->mockClient([new Response(200, [], '56')]);
        $client->orders()->refund(7);

        $body = json_decode((string) $this->lastRequest()->getBody(), true);
        self::assertArrayNotHasKey('items', $body);
        self::assertArrayNotHasKey('arguments', $body);
        self::assertFalse($body['notify']);
    }

    public function testRefundInvoiceHitsInvoicePathAndIsOnlineByDefault(): void
    {
        $client = $this->mockClient([new Response(200, [], '77')]);
        $creditMemoId = $client->orders()->refundInvoice(31);

        self::assertSame(77, $creditMemoId);
        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://example.test/rest/V1/invoice/31/refund', (string) $request->getUri());
        $body = json_decode((string) $request->getBody(), true);
        self::assertTrue($body['isOnline']);
        self::assertArrayNotHasKey('items', $body);
    }

    public function testRefundInvoiceCanRefundOfflineWithItems(): void
    {
        $client = $this->mockClient([new Response(200, [], '78')]);
        $client->orders()->refundInvoice(31, [['order_item_id' => 11, 'qty' => 1]], isOnline: false);

        $body = json_decode((string) $this->lastRequest()->getBody(), true);
        self::assertFalse($body['isOnline']);
        self::assertSame([['order_item_id' => 11, 'qty' => 1]], $body['items']);
    }

    public function testIdReturningCallsRefuseAResponseThatIsNotAnId(): void
    {
        // (int) would turn these into the ids 1 and 0.
        foreach (['{"unexpected":"shape"}', '', 'true', '"id_5"'] as $body) {
            $client = $this->mockClient([new Response(200, [], $body)]);

            try {
                $client->orders()->invoice(7);
                self::fail("Expected UnexpectedResponseException for body: {$body}");
            } catch (UnexpectedResponseException $e) {
                self::assertStringContainsString('invoice id', $e->getMessage());
            }
        }

        $client = $this->mockClient([new Response(200, [], '"42"')]);
        self::assertSame(42, $client->orders()->ship(7));
    }

    public function testGetStatusRefusesAResponseThatIsNotAStatusCode(): void
    {
        $client = $this->mockClient([new Response(200, [], '{"message":"x"}')]);

        $this->expectException(UnexpectedResponseException::class);
        $client->orders()->getStatus(7);
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
