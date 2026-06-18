# Magento 2 PHP SDK

A small, **framework-independent** PHP client for the Magento 2 REST API. No
Laravel/Symfony/CodeIgniter coupling — just PHP 8.1+ and Guzzle. It wraps the
endpoints you actually reach for when integrating a catalog:

- **Products** — get / create / update / delete, search, bulk SKU → id resolution
- **Product attributes** — get, options, create, idempotent option management
- **Attribute sets** — list groups, list assigned attributes, assign an attribute
- **Categories** — tree, get, flat list, create / update / delete, (un)assign products
- **Stock** — set quantity / status per SKU (legacy stock items + MSI source items)
- **Prices** — base prices and special (sale) prices
- **Product media** — list / add / update / remove gallery entries
- **Configurable products** — link & unlink simple children, manage option axes
- **Orders** — get / search, create, invoice, ship, cancel, status
- **Customers** — get / search, create / update (with inline addresses), delete
- **Async bulk API** — queue product batches (single or concurrent), poll status

It also gives you fluent value-object builders (`Product`, `StockItem`, `Price`,
`Category`, `MediaEntry`, `AttributeOption`, `Customer`, `Address`) that every
write method accepts interchangeably with plain arrays, a fluent `SearchCriteria`
builder, typed enums (`ProductStatus`, `Visibility`, `FrontendInput`,
`BulkOperationStatus`, `OrderState`), and a clean exception hierarchy instead of
leaking Guzzle exceptions.

> Scope: this targets the **standard** Magento 2 REST API. Custom-module
> endpoints (e.g. marketplace extensions) are intentionally out of scope.

## Install

```bash
composer require baldie81/magento2-sdk
```

## Quick start

```php
use Baldie81\Magento2SDK\Configuration;
use Baldie81\Magento2SDK\MagentoClient;

$magento = MagentoClient::create(new Configuration(
    baseUrl: 'https://shop.example.com',
    token:   'your-integration-token',
    // timeout: 30.0, connectTimeout: 10.0, verifySsl: true, storeCode: null,
));

$product = $magento->products()->get('SKU-123');
```

Authentication is a bearer token — a Magento **Integration** access token, or an
admin token you obtain yourself. TLS verification is **on** by default; pass
`verifySsl: false` only for local/self-signed environments.

## Value objects (or arrays)

Every write method accepts **either** a fluent builder **or** a raw array, so you
can opt into type-safety without giving up the array escape hatch. The builders
(`Product`, `StockItem`, `Price`, `Category`, `MediaEntry`, `AttributeOption`,
`Customer`, `Address`) all implement `Arrayable`:

```php
use Baldie81\Magento2SDK\Support\Product;
use Baldie81\Magento2SDK\Support\MediaEntry;
use Baldie81\Magento2SDK\Enum\ProductStatus;
use Baldie81\Magento2SDK\Enum\Visibility;

// Builder — a simple with an image attached:
$magento->products()->create(
    Product::make('SKU-123')
        ->name('Example')->attributeSetId(4)->type('simple')
        ->price(19.90)->status(ProductStatus::Enabled)->visibility(Visibility::CatalogSearch)
        ->websiteIds([1])
        ->customAttribute('color', 49)
        ->mediaGalleryEntry(
            MediaEntry::image($base64, 'image/jpeg', 'front.jpg')->roles(['image'])
        )
);

// A configurable carries its axis and child links in the same builder:
$magento->products()->create(
    Product::make('PARENT-SKU')
        ->name('Parent')->attributeSetId(4)->type('configurable')->visibility(Visibility::CatalogSearch)
        ->websiteIds([1])
        ->configurableOption(93, [49, 239], 'Color') // axis: attribute id + option ids
        ->configurableLinks([10, 11])                // child entity ids
);

// …or the exact same calls with raw arrays — both are accepted everywhere:
$magento->products()->create([
    'sku' => 'SKU-123', 'name' => 'Example', 'attribute_set_id' => 4,
    'type_id' => 'simple', 'price' => 19.90,
]);
```

`->set('field', $value)` on any builder is the escape hatch for fields it doesn't
model explicitly. The builders produce the **inner** body — the resource adds the
`{"product": …}` / `{"category": …}` / `{"stockItem": …}` wrapper.

## Products

```php
use Baldie81\Magento2SDK\Enum\ProductStatus;

$magento->products()->create([
    'sku'              => 'SKU-123',
    'name'             => 'Example',
    'attribute_set_id' => 4,
    'price'            => '19.90',
    'type_id'          => 'simple',
]);

$magento->products()->update('SKU-123', ['sku' => 'SKU-123', 'price' => '17.90']);
$magento->products()->setStatus('SKU-123', ProductStatus::Disabled);
$magento->products()->disable('SKU-123'); // shorthand for the above
$magento->products()->enable('SKU-123');
$magento->products()->delete('SKU-123');

// Resolve many SKUs to entity ids (auto-chunked + paginated to dodge 414s).
$idsBySku = $magento->products()->searchBySkus(['A-1', 'B-2', 'C-3']);
```

## Search criteria

```php
use Baldie81\Magento2SDK\Support\SearchCriteria;

$criteria = SearchCriteria::create()
    ->where('status', 1)            // AND group
    ->where('price', 100, 'gt')     // AND group
    ->whereAny([                    // one OR group
        ['type_id', 'simple'],
        ['type_id', 'configurable'],
    ])
    ->sort('created_at', 'DESC');

// One page (the raw Magento envelope: items, total_count, search_criteria):
$result = $magento->products()->search($criteria->pageSize(50)->currentPage(1));

// Or every page, merged — see "Pagination" below:
$allItems = $magento->products()->searchAll($criteria);
```

`where()` adds an AND group; `whereAny()` adds a single group of OR'd filters —
matching Magento's filter-group semantics. Array values with the `in`/`nin`
condition are comma-joined for you.

## Pagination

Magento caps `searchCriteria[pageSize]` server-side (300 by default) and
silently truncates anything larger — so a single `search()` can quietly miss
results. The `searchAll()` / `listFlat()` methods walk every page for you
(capping the page size at 300, using `total_count` to know when to stop) and
return the merged `items`:

```php
$products   = $magento->products()->searchAll($criteria);   // list of items
$attributes = $magento->attributes()->searchAll($criteria);
$categories = $magento->categories()->listFlat();            // every category
```

`searchBySkus()` paginates the same way (and additionally chunks the SKU set so
the `sku in (...)` filter never overflows the URL).

## Attributes & attribute sets

```php
use Baldie81\Magento2SDK\Enum\FrontendInput;

$magento->attributes()->create([
    'attribute_code' => 'color',
    'frontend_input' => FrontendInput::Select, // or the plain string 'select'
    'default_frontend_label' => 'Color',
    'scope' => 'global',
]);
// Add an option with per-store-view (per-language) labels:
use Baldie81\Magento2SDK\Support\AttributeOption;

$optionId = $magento->attributes()->addOption('color',
    AttributeOption::create('Red')
        ->withStoreLabel(1, 'Κόκκινο')   // store view 1
        ->withStoreLabel(2, 'Rouge')     // store view 2
);

// A raw array still works for full control:
$magento->attributes()->addOption('color', ['label' => 'Blue']);

$options = $magento->attributes()->options('color');

// Idempotent: returns the existing option's id if one already matches the admin
// label (case-insensitive), otherwise creates it — so "Black"/"black"/"bLack"
// never become three options.
$id = $magento->attributes()->ensureOption('color', AttributeOption::create('Black'));

// Just look one up (case-insensitively) without creating:
$id = $magento->attributes()->findOption('color', 'black'); // string|null

// Create-or-update: refreshes an existing option's translations instead of
// skipping it (ensureOption leaves an existing option untouched).
$magento->attributes()->upsertOption('color',
    AttributeOption::create('Black')->withStoreLabel(1, 'Μαύρο')
);

// Bulk idempotent insert: one lookup up front, then create only what's
// missing. Accepts labels, AttributeOptions, or raw arrays — and dedupes
// "Black"/"black" within the batch. Returns label => option id.
$ids = $magento->attributes()->ensureOptions('color', [
    'Black',
    'White',
    AttributeOption::create('Red')->withStoreLabel(1, 'Κόκκινο'),
]);
// $ids === ['Black' => '5', 'White' => '3', 'Red' => 'id_9']

// The low-level map is still available if you want to drive it yourself:
$existing = $magento->attributes()->optionMap('color'); // ['black' => '5', ...]
```

`optionMap()` is fetched **once per attribute and cached in memory** for the
client's lifetime — so importing many products that each ensure options doesn't
refetch the option list every time. Options created through the SDK update the
cache automatically. Force a reload with `optionMap($code, refresh: true)` or
drop it with `clearOptionCache()`:

```php
$magento->attributes()->ensureOptions('color', $colorsForProductA); // 1 lookup
$magento->attributes()->ensureOptions('color', $colorsForProductB); // reuses the cache
$magento->attributes()->clearOptionCache('color');                  // next call refetches

$groups = $magento->attributeSets()->groups(4);
$magento->attributeSets()->assignAttribute(4, $groups[0]['attribute_group_id'], 'color');
```

## Stock, prices, media & categories

Single-request writes for the rest of the catalog:

```php
// Stock — legacy single-SKU stock item, or MSI source items.
$magento->stock()->setQuantity('SKU-1', 12);            // is_in_stock derived from qty
$magento->stock()->update('SKU-1', ['qty' => 12, 'is_in_stock' => true]);
$magento->stock()->saveSourceItems([
    ['sku' => 'SKU-1', 'source_code' => 'default', 'quantity' => 12, 'status' => 1],
]);

// Prices — base and special (sale). Both return the items that FAILED.
$magento->prices()->setBasePrice('SKU-1', 19.90);
$magento->prices()->setSpecialPrices([
    ['sku' => 'SKU-1', 'store_id' => 0, 'price' => 14.90, 'price_from' => '2026-06-01 00:00:00'],
]);

// Categories — writes + product assignment.
$cat = $magento->categories()->create(['name' => 'Shoes', 'parent_id' => 2]);
$magento->categories()->assignProduct($cat['id'], 'SKU-1', position: 1);
$magento->categories()->removeProduct($cat['id'], 'SKU-1');

// Product media.
$entryId = $magento->media()->create('SKU-1', [
    'media_type' => 'image',
    'label'      => 'Front',
    'types'      => ['image', 'small_image', 'thumbnail'],
    'content'    => ['base64_encoded_data' => $base64, 'type' => 'image/jpeg', 'name' => 'front.jpg'],
]);
$magento->media()->delete('SKU-1', $entryId);
```

## Configurable products

Link simple children to a configurable parent and manage its option axes:

```php
$magento->configurables()->addOption('PARENT-SKU', [
    'attribute_id' => '93',                 // e.g. the "size" attribute
    'label'        => 'Size',
    'values'       => [['value_index' => 167], ['value_index' => 168]],
]);

$magento->configurables()->addChild('PARENT-SKU', 'CHILD-SKU-S');   // link a simple
$magento->configurables()->addChild('PARENT-SKU', 'CHILD-SKU-M');
$magento->configurables()->children('PARENT-SKU');                  // list children
$magento->configurables()->removeChild('PARENT-SKU', 'CHILD-SKU-S'); // unlink
```

## Orders

```php
use Baldie81\Magento2SDK\Support\SearchCriteria;
use Baldie81\Magento2SDK\Support\OrderStatus;
use Baldie81\Magento2SDK\Enum\OrderState;

$order = $magento->orders()->get(7);

// getStatus() returns an OrderStatus value object:
$status = $magento->orders()->getStatus(7);
$status->code();    // 'processing'
$status->label();   // 'Processing'
$status->state();   // OrderState::Processing (null for custom statuses)
$status->equals(OrderStatus::PROCESSING); // true

// Order statuses are admin-configurable, so they're a value object, not an
// enum. Teach the SDK your store's custom statuses once at bootstrap (labels
// optional) and they become "known":
OrderStatus::register('awaiting_pickup', 'Awaiting Pickup');
OrderStatus::registerMany(['in_transit' => 'In Transit', 'delivered' => null]);

// Find orders to act on (paginates every page; see "Pagination"):
$processing = $magento->orders()->searchAll(
    SearchCriteria::create()->where('state', OrderState::Processing->value)
);

// Invoice — whole order, capturing payment online by default, returns the invoice id:
$invoiceId = $magento->orders()->invoice(7);
// …or a partial invoice without capture (e.g. an offline/authorize-only payment):
$magento->orders()->invoice(7, [['order_item_id' => 11, 'qty' => 2]], capture: false, comment: 'Backorder split');

// Ship — whole order or specific items, optionally with tracking, returns the shipment id:
$shipmentId = $magento->orders()->ship(7,
    items:  [['order_item_id' => 11, 'qty' => 1]],
    tracks: [['track_number' => '1Z999', 'title' => 'UPS', 'carrier_code' => 'ups']],
    notify: true,
);

// Cancel — returns false if Magento refuses (already invoiced/shipped/complete):
$magento->orders()->cancel(7);
```

Order creation goes through the repository (`POST /V1/orders`); pass the full
order entity (array or any `Arrayable`) and the SDK adds the `{"entity": …}`
wrapper. Note Magento's own path quirk, preserved here: invoice/ship use the
**singular** `V1/order/{id}/…` while get/create/cancel use plural `V1/orders`.

## Customers

```php
use Baldie81\Magento2SDK\Support\Customer;
use Baldie81\Magento2SDK\Support\Address;

$customer = $magento->customers()->get(5);

// Create an account. Pass a password, or omit it to email a "set password" link.
$magento->customers()->create(
    Customer::make('jane@example.com')
        ->firstname('Jane')->lastname('Doe')->websiteId(1)->groupId(1)
        ->address(
            Address::make()
                ->firstname('Jane')->lastname('Doe')
                ->street(['123 Oak Ave'])->city('Austin')
                ->region('Texas', regionId: 57)->postcode('78758')->countryId('US')
                ->telephone('512-555-1111')
                ->defaultBilling()->defaultShipping()
        ),
    password: 's3cret!',
);

// Update (repository save). Addresses are saved inline: send the full set you
// want to keep, each existing one carrying its id.
$magento->customers()->update(5, Customer::make('jane@example.com')->firstname('Janet'));

$magento->customers()->searchAll(SearchCriteria::create()->where('group_id', 1));
$magento->customers()->deleteAddress(12); // the one dedicated address route
$magento->customers()->delete(5);
```

Addresses are not a separate write endpoint in core Magento — they live on the
customer, so you add/edit them via `Customer::address()` and re-saving. Only
deletion has its own route (`deleteAddress()`).

## Async bulk

```php
use Baldie81\Magento2SDK\Enum\BulkOperationStatus;

// One batch:
$response = $magento->bulk()->createProducts([
    ['product' => [/* ... */]],
    ['product' => [/* ... */]],
]);
$uuid = $response['bulk_uuid'];

// Many batches, fired concurrently in waves:
$chunks  = array_chunk($payloads, 500);
$results = $magento->bulk()->dispatchProducts($chunks, concurrency: 4);
// $results[$i] === ['bulk_uuid' => '...'] or ['error' => '...']

// Poll once the queue consumer has run:
foreach ($magento->bulk()->detailedStatus($uuid)['operations_list'] ?? [] as $op) {
    $status = BulkOperationStatus::from($op['status']);
    if ($status->isFailure()) {
        // $op['result_message']
    }
}
```

Bulk work is processed asynchronously by Magento's message-queue consumers, so a
`bulk_uuid` means *queued*, not *done* — poll `status()` / `detailedStatus()`.

## Errors

Every failure is a `Baldie81\Magento2SDK\Exception\MagentoException`:

- `ApiException` — Magento returned 4xx/5xx. Exposes `->statusCode`,
  `->rawBody`, `->parameters`, and a message with Magento's `%1`/`%name`
  placeholders already interpolated.
- `TransportException` — connection/timeout/TLS failure, no HTTP response.

```php
use Baldie81\Magento2SDK\Exception\ApiException;

try {
    $magento->products()->get('missing');
} catch (ApiException $e) {
    $e->statusCode;  // e.g. 404
    $e->getMessage(); // "Requested product doesn't exist"
}
```

## Bring your own transport

Need custom middleware, retries, or logging? Build the Guzzle client yourself
and hand it over — its `base_uri` must point at the store root:

```php
use GuzzleHttp\Client;
use Baldie81\Magento2SDK\MagentoClient;

$guzzle  = new Client([/* base_uri, handler stack, ... */]);
$magento = MagentoClient::fromGuzzle($guzzle, restPrefix: 'rest/');
```

## Store scoping

Store-specific values are saved by hitting the store-scoped REST path
(`rest/<code>/V1/...`). Either scope a whole client at construction:

```php
new Configuration(baseUrl: '...', token: '...', storeCode: 'fr');
```

…or derive a store-scoped client on the fly with `forStore()` — it shares the
same transport and leaves the original (default-scope) client untouched:

```php
// create globally, then override only the store-scoped fields per store view
$magento->products()->create(Product::make('SKU-1')->name('Dress')->attributeSetId(4)->type('simple'));
$magento->forStore('fr')->products()->update('SKU-1', Product::make('SKU-1')->name('Robe'));
```

(Multi-language *option* labels are separate — see `AttributeOption::withStoreLabel()`.)

## Tests

```bash
composer install
composer test
```

## License

MIT.
