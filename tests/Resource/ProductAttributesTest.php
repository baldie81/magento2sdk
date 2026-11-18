<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK\Tests\Resource;

use Baldie81\Magento2SDK\Enum\FrontendInput;
use Baldie81\Magento2SDK\Support\AttributeOption;
use Baldie81\Magento2SDK\Tests\MockClientTestCase;
use GuzzleHttp\Psr7\Response;

final class ProductAttributesTest extends MockClientTestCase
{
    public function testCreateNormalisesFrontendInputEnumToString(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode(['attribute_code' => 'color']))]);

        $client->attributes()->create([
            'attribute_code' => 'color',
            'frontend_input' => FrontendInput::Multiselect,
        ]);

        $body = json_decode((string) $this->lastRequest()->getBody(), true);
        self::assertSame('multiselect', $body['attribute']['frontend_input']);
    }

    public function testCreateLeavesPlainStringFrontendInputUntouched(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode(['attribute_code' => 'color']))]);

        $client->attributes()->create([
            'attribute_code' => 'color',
            'frontend_input' => 'select',
        ]);

        $body = json_decode((string) $this->lastRequest()->getBody(), true);
        self::assertSame('select', $body['attribute']['frontend_input']);
    }

    public function testEnsureOptionReturnsExistingIdCaseInsensitivelyWithoutCreating(): void
    {
        $client = $this->mockClient([
            new Response(200, [], json_encode([
                ['label' => ' ', 'value' => ''],     // Magento placeholder
                ['label' => 'White', 'value' => '3'],
                ['label' => 'Black', 'value' => '5'],
            ])),
        ]);

        $id = $client->attributes()->ensureOption('color', AttributeOption::create('bLACK'));

        self::assertSame('5', $id);
        self::assertCount(1, $this->history); // only the options lookup, no POST
        self::assertSame('GET', $this->history[0]['request']->getMethod());
    }

    public function testEnsureOptionCreatesWhenAbsent(): void
    {
        $client = $this->mockClient([
            new Response(200, [], json_encode([['label' => 'White', 'value' => '3']])),
            new Response(200, [], json_encode('id_9')),
        ]);

        $id = $client->attributes()->ensureOption('color', AttributeOption::create('Black'));

        self::assertSame('9', $id);
        self::assertCount(2, $this->history);
        self::assertSame('POST', $this->history[1]['request']->getMethod());
    }

    public function testFindOptionReturnsNullWhenNoMatch(): void
    {
        $client = $this->mockClient([
            new Response(200, [], json_encode([['label' => 'White', 'value' => '3']])),
        ]);

        self::assertNull($client->attributes()->findOption('color', 'Black'));
    }

    public function testOptionMapReturnsNormalisedLabelToIdSkippingPlaceholder(): void
    {
        $client = $this->mockClient([
            new Response(200, [], json_encode([
                ['label' => ' ', 'value' => ''],
                ['label' => 'White', 'value' => '3'],
                ['label' => 'Black', 'value' => '5'],
            ])),
        ]);

        self::assertSame(
            ['white' => '3', 'black' => '5'],
            $client->attributes()->optionMap('color'),
        );
    }

    public function testUpsertOptionUpdatesExistingThroughTheUpdateRoute(): void
    {
        $client = $this->mockClient([
            new Response(200, [], json_encode([['label' => 'Black', 'value' => '5']])),
            new Response(200, [], json_encode(true)),
        ]);

        $id = $client->attributes()->upsertOption('color',
            AttributeOption::create('black')->withStoreLabel(1, 'Μαύρο'),
        );

        self::assertSame('5', $id);
        self::assertCount(2, $this->history);
        $request = $this->history[1]['request'];
        self::assertSame('PUT', $request->getMethod());
        self::assertStringEndsWith('V1/products/attributes/color/options/5', $request->getUri()->getPath());
        $body = json_decode((string) $request->getBody(), true);
        self::assertSame('5', $body['option']['value']);
        self::assertSame([['store_id' => 1, 'label' => 'Μαύρο']], $body['option']['store_labels']);
    }

    public function testUpsertOptionFallsBackToRepostWhenUpdateRouteIsMissing(): void
    {
        $client = $this->mockClient([
            new Response(200, [], json_encode([['label' => 'Black', 'value' => '5']])),
            new Response(404, [], json_encode(['message' => 'Request does not match any route.'])),
            new Response(200, [], json_encode('id_5')),
        ]);

        $id = $client->attributes()->upsertOption('color', AttributeOption::create('Black'));

        self::assertSame('5', $id);
        self::assertCount(3, $this->history);
        $request = $this->history[2]['request'];
        self::assertSame('POST', $request->getMethod());
        $body = json_decode((string) $request->getBody(), true);
        self::assertSame('5', $body['option']['value']); // re-posted with the existing id => update
    }

    public function testAddOptionLooksTheIdUpWhenMagentoReturnsOnlyTrue(): void
    {
        $client = $this->mockClient([
            new Response(200, [], json_encode(true)), // older releases return a bare true
            new Response(200, [], json_encode([['label' => 'Red', 'value' => '12']])),
        ]);

        self::assertSame('12', $client->attributes()->addOption('color', ['label' => 'Red']));
    }

    public function testAddOptionAcceptsAnAlreadyNumericId(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode('12'))]);

        self::assertSame('12', $client->attributes()->addOption('color', ['label' => 'Red']));
        self::assertCount(1, $this->history);
    }

    public function testOptionMapReadsThroughTheUnscopedPathOnAStoreScopedClient(): void
    {
        $client = $this->mockClient([
            new Response(200, [], json_encode([['label' => 'Black', 'value' => '5']])),
        ]);

        self::assertSame('5', $client->forStore('fr')->attributes()->findOption('color', 'black'));
        self::assertSame('/rest/V1/products/attributes/color/options', $this->lastRequest()->getUri()->getPath());
    }

    public function testEnsureOptionsCreatesOnlyMissingWithOneLookup(): void
    {
        $client = $this->mockClient([
            new Response(200, [], json_encode([
                ['label' => 'White', 'value' => '3'],
                ['label' => 'Black', 'value' => '5'],
            ])),
            new Response(200, [], json_encode('id_9')), // the one create, for Red
        ]);

        $ids = $client->attributes()->ensureOptions('color', ['Black', 'white', 'Red']);

        // existing matched case-insensitively; only the missing one is created.
        self::assertSame(['Black' => '5', 'white' => '3', 'Red' => '9'], $ids);
        self::assertCount(2, $this->history); // 1 lookup + 1 create
        self::assertSame('GET', $this->history[0]['request']->getMethod());
        self::assertSame('POST', $this->history[1]['request']->getMethod());
    }

    public function testEnsureOptionsDedupesWithinBatch(): void
    {
        $client = $this->mockClient([
            new Response(200, [], json_encode([])),    // no existing options
            new Response(200, [], json_encode('id_1')), // single create
        ]);

        $ids = $client->attributes()->ensureOptions('color', ['Black', 'black', 'bLACK']);

        self::assertSame(['Black' => '1', 'black' => '1', 'bLACK' => '1'], $ids);
        self::assertCount(2, $this->history); // lookup + exactly one create
    }

    public function testEnsureOptionsCreatesMissingWithStoreLabels(): void
    {
        $client = $this->mockClient([
            new Response(200, [], json_encode([])),
            new Response(200, [], json_encode('id_7')),
        ]);

        $client->attributes()->ensureOptions('color', [
            AttributeOption::create('Red')->withStoreLabel(1, 'Κόκκινο'),
        ]);

        $body = json_decode((string) $this->history[1]['request']->getBody(), true);
        self::assertSame('Red', $body['option']['label']);
        self::assertSame([['store_id' => 1, 'label' => 'Κόκκινο']], $body['option']['store_labels']);
    }

    public function testOptionMapIsCachedUntilRefreshed(): void
    {
        $client = $this->mockClient([
            new Response(200, [], json_encode([['label' => 'Black', 'value' => '5']])),
            new Response(200, [], json_encode([
                ['label' => 'Black', 'value' => '5'],
                ['label' => 'White', 'value' => '3'],
            ])),
        ]);
        $attrs = $client->attributes();

        $first  = $attrs->optionMap('color');
        $second = $attrs->optionMap('color'); // served from memory, no new GET

        self::assertSame($first, $second);
        self::assertCount(1, $this->history);

        $refreshed = $attrs->optionMap('color', refresh: true); // forces a refetch
        self::assertCount(2, $this->history);
        self::assertArrayHasKey('white', $refreshed);
    }

    public function testEnsureOptionsReusesInMemoryMapAcrossCalls(): void
    {
        $client = $this->mockClient([
            new Response(200, [], json_encode([])),     // a single options lookup, ever
            new Response(200, [], json_encode('id_1')),  // create Black (first call)
            new Response(200, [], json_encode('id_2')),  // create White (second call)
        ]);
        $attrs = $client->attributes();

        $first  = $attrs->ensureOptions('color', ['Black']);
        $second = $attrs->ensureOptions('color', ['black', 'White']); // 'black' hits cache

        self::assertSame(['Black' => '1'], $first);
        self::assertSame(['black' => '1', 'White' => '2'], $second);
        // 1 GET + 2 POSTs — crucially NOT a second options lookup.
        self::assertSame(['GET', 'POST', 'POST'], array_map(
            static fn ($t) => $t['request']->getMethod(),
            $this->history,
        ));
    }

    public function testClearOptionCacheForcesRefetch(): void
    {
        $client = $this->mockClient([
            new Response(200, [], json_encode([['label' => 'Black', 'value' => '5']])),
            new Response(200, [], json_encode([['label' => 'Black', 'value' => '5']])),
        ]);
        $attrs = $client->attributes();

        $attrs->optionMap('color');
        $attrs->clearOptionCache('color');
        $attrs->optionMap('color');

        self::assertCount(2, $this->history);
    }

    public function testUpsertOptionCreatesWhenAbsent(): void
    {
        $client = $this->mockClient([
            new Response(200, [], json_encode([['label' => 'White', 'value' => '3']])),
            new Response(200, [], json_encode('id_9')),
        ]);

        $id = $client->attributes()->upsertOption('color', AttributeOption::create('Black'));

        self::assertSame('9', $id);
        $body = json_decode((string) $this->history[1]['request']->getBody(), true);
        self::assertArrayNotHasKey('value', $body['option']); // a plain create, no id injected
    }

    public function testAddOptionSendsMultiLanguageStoreLabels(): void
    {
        $client = $this->mockClient([new Response(200, [], json_encode('id_5'))]);

        $optionId = $client->attributes()->addOption('color',
            AttributeOption::create('Red')
                ->withStoreLabel(1, 'Κόκκινο')
                ->withStoreLabel(2, 'Rouge'),
        );

        self::assertSame('5', $optionId);
        $body = json_decode((string) $this->lastRequest()->getBody(), true);
        self::assertSame('Red', $body['option']['label']);
        self::assertSame(
            [
                ['store_id' => 1, 'label' => 'Κόκκινο'],
                ['store_id' => 2, 'label' => 'Rouge'],
            ],
            $body['option']['store_labels'],
        );
    }

    public function testUpdateSavesAttributeWithCodeInjected(): void
    {
        $client = $this->mockClient([new Response(200, [], (string) json_encode([
            'attribute_code' => 'color', 'default_frontend_label' => 'Colour',
        ]))]);

        $result = $client->attributes()->update('color', ['default_frontend_label' => 'Colour']);

        self::assertSame('Colour', $result['default_frontend_label']);
        $request = $this->lastRequest();
        self::assertSame('PUT', $request->getMethod());
        self::assertStringContainsString('V1/products/attributes/color', (string) $request->getUri());
        $body = json_decode((string) $request->getBody(), true);
        self::assertSame('Colour', $body['attribute']['default_frontend_label']);
        self::assertSame('color', $body['attribute']['attribute_code']); // injected automatically
    }

    public function testDeleteDeletesAttribute(): void
    {
        $client = $this->mockClient([new Response(200, [], 'true')]);

        self::assertTrue($client->attributes()->delete('color'));
        self::assertSame('DELETE', $this->lastRequest()->getMethod());
        self::assertStringContainsString('V1/products/attributes/color', (string) $this->lastRequest()->getUri());
    }
}
