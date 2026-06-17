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

        self::assertSame('id_9', $id);
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

    public function testUpsertOptionUpdatesExistingByRepostingWithItsValue(): void
    {
        $client = $this->mockClient([
            new Response(200, [], json_encode([['label' => 'Black', 'value' => '5']])),
            new Response(200, [], json_encode('5')),
        ]);

        $id = $client->attributes()->upsertOption('color',
            AttributeOption::create('black')->withStoreLabel(1, 'Μαύρο'),
        );

        self::assertSame('5', $id);
        self::assertCount(2, $this->history);
        $request = $this->history[1]['request'];
        self::assertSame('POST', $request->getMethod());
        $body = json_decode((string) $request->getBody(), true);
        self::assertSame('5', $body['option']['value']); // re-posted with the existing id => update
        self::assertSame([['store_id' => 1, 'label' => 'Μαύρο']], $body['option']['store_labels']);
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
        self::assertSame(['Black' => '5', 'white' => '3', 'Red' => 'id_9'], $ids);
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

        self::assertSame(['Black' => 'id_1', 'black' => 'id_1', 'bLACK' => 'id_1'], $ids);
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

        self::assertSame(['Black' => 'id_1'], $first);
        self::assertSame(['black' => 'id_1', 'White' => 'id_2'], $second);
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

        self::assertSame('id_9', $id);
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

        self::assertSame('id_5', $optionId);
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
}
