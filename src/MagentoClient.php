<?php

declare(strict_types=1);

namespace Baldie81\Magento2SDK;

use GuzzleHttp\ClientInterface;
use Baldie81\Magento2SDK\Http\HttpClient;
use Baldie81\Magento2SDK\Resource\AttributeSets;
use Baldie81\Magento2SDK\Resource\Bulk;
use Baldie81\Magento2SDK\Resource\Categories;
use Baldie81\Magento2SDK\Resource\ConfigurableProducts;
use Baldie81\Magento2SDK\Resource\Customers;
use Baldie81\Magento2SDK\Resource\Orders;
use Baldie81\Magento2SDK\Resource\Prices;
use Baldie81\Magento2SDK\Resource\ProductAttributes;
use Baldie81\Magento2SDK\Resource\ProductMedia;
use Baldie81\Magento2SDK\Resource\Products;
use Baldie81\Magento2SDK\Resource\Stock;

/**
 * Entry point for the SDK. Exposes the Magento REST resources as lazily-created
 * sub-clients.
 *
 *   $magento = MagentoClient::create(new Configuration(
 *       baseUrl: 'https://shop.example.com',
 *       token:   'integration-token',
 *   ));
 *   $magento->products()->get('SKU-123');
 */
final class MagentoClient
{
    private ?Products $products = null;
    private ?ProductAttributes $attributes = null;
    private ?AttributeSets $attributeSets = null;
    private ?Categories $categories = null;
    private ?Stock $stock = null;
    private ?Prices $prices = null;
    private ?ProductMedia $media = null;
    private ?ConfigurableProducts $configurables = null;
    private ?Bulk $bulk = null;
    private ?Orders $orders = null;
    private ?Customers $customers = null;

    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Build a client from connection settings (the common case).
     */
    public static function create(Configuration $config): self
    {
        return new self(new HttpClient(ClientFactory::create($config), $config->restPrefix()));
    }

    /**
     * Build a client around a pre-configured Guzzle client, for callers who need
     * custom middleware/handlers. The Guzzle client's base_uri must point at the
     * store root and end in a slash; `$restPrefix` is prepended to every REST
     * path. The client supplies its own Authorization header. Whatever it is
     * configured with, error statuses always throw and redirects are never
     * followed.
     */
    public static function fromGuzzle(ClientInterface $client, string $restPrefix = 'rest/'): self
    {
        return new self(new HttpClient($client, $restPrefix));
    }

    public function http(): HttpClient
    {
        return $this->http;
    }

    /**
     * A client scoped to a store view, so writes land at that store's scope
     * (`rest/<storeCode>/V1/...`). Shares the same underlying transport; an empty
     * code returns the default (unscoped) view.
     *
     *   $magento->forStore('fr')->products()->update('SKU-1', Product::make('SKU-1')->name('Robe'));
     *
     * @throws \InvalidArgumentException when the code isn't a valid store code
     */
    public function forStore(string $storeCode): self
    {
        $code   = Configuration::normalizeStoreCode($storeCode);
        $prefix = $code === '' ? 'rest/' : "rest/$code/";

        return new self($this->http->withRestPrefix($prefix));
    }

    public function products(): Products
    {
        return $this->products ??= new Products($this->http);
    }

    public function attributes(): ProductAttributes
    {
        return $this->attributes ??= new ProductAttributes($this->http);
    }

    public function attributeSets(): AttributeSets
    {
        return $this->attributeSets ??= new AttributeSets($this->http);
    }

    public function categories(): Categories
    {
        return $this->categories ??= new Categories($this->http);
    }

    public function stock(): Stock
    {
        return $this->stock ??= new Stock($this->http);
    }

    public function prices(): Prices
    {
        return $this->prices ??= new Prices($this->http);
    }

    public function media(): ProductMedia
    {
        return $this->media ??= new ProductMedia($this->http);
    }

    public function configurables(): ConfigurableProducts
    {
        return $this->configurables ??= new ConfigurableProducts($this->http);
    }

    public function bulk(): Bulk
    {
        return $this->bulk ??= new Bulk($this->http);
    }

    public function orders(): Orders
    {
        return $this->orders ??= new Orders($this->http);
    }

    public function customers(): Customers
    {
        return $this->customers ??= new Customers($this->http);
    }
}
