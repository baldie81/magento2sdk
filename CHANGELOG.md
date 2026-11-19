# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-11-19

Initial release: a framework-independent PHP 8.1+ client for the Magento 2 REST
API, built on Guzzle.

### Added

- **Products** — get, create, update, delete, enable/disable, `search()` and
  `searchAll()`, and `searchBySkus()` to resolve many SKUs to entity ids. It
  chunks the SKU set by count and by encoded URL length, looks up SKUs
  containing a comma separately, and keys the result by the SKUs as requested
  (Magento matches them case-insensitively).
- **Product attributes** — get, create, update, delete, search, and option
  management: `addOption()`, `findOption()`, `ensureOption()`,
  `ensureOptions()`, `upsertOption()` and a cached `optionMap()`. Options are
  matched by admin label case-insensitively, always return the real option id
  whatever the Magento version answers (`"id_9"`, `"9"` or `true`), and are
  looked up through the unscoped path so store-view translations can't cause
  duplicates. `upsertOption()` uses the dedicated update route (Magento 2.4.1+)
  and falls back to re-posting on older releases.
- **Attribute sets** — `all()`, `groups()`, `attributes()`, `create()`,
  `delete()`, `assignAttribute()`, `createGroup()`, `deleteGroup()` and
  `setGroupSortOrder()`.
- **Categories** — tree, get, flat list, create, update, delete, and product
  assignment.
- **Stock** — legacy stock items and MSI source items.
- **Prices** — base prices and special (sale) prices.
- **Product media** — list, get, create, update and delete gallery entries.
- **Configurable products** — link and unlink children, manage option axes.
- **Orders** — get, search, create, `getStatus()`, `invoice()`, `ship()`,
  `refund()` (offline), `refundInvoice()` (online by default) and `cancel()`.
- **Customers** — get, search, create (with an optional password), update with
  inline addresses, delete, and `deleteAddress()`.
- **Async bulk API** — `createProducts()`, concurrent `dispatchProducts()`,
  `status()` and `detailedStatus()`.
- **Value-object builders** (`Product`, `StockItem`, `Price`, `Category`,
  `MediaEntry`, `AttributeOption`, `Customer`, `Address`) accepted
  interchangeably with plain arrays by every write method, a fluent
  `SearchCriteria` builder, the `OrderStatus` value object with a registry for
  custom statuses, and typed enums (`ProductStatus`, `Visibility`,
  `FrontendInput`, `BulkOperationStatus`, `OrderState`).
- **Pagination** — `searchAll()` / `listFlat()` walk every page, treating
  `total_count` as authoritative and stopping on an empty or repeated page. A
  stable default sort is added when the criteria has none; if the store rejects
  it (HTTP 400) the walk is retried without it.
- **Store scoping** — `Configuration::$storeCode` and
  `MagentoClient::forStore()` for per-store-view requests.
- **Bring your own transport** — `MagentoClient::fromGuzzle()`.
- **Exceptions** — every failed request is a `MagentoException`:
  - `ApiException` for 4xx/5xx, redirects and non-JSON 2xx bodies, with
    `$statusCode`, `$rawBody`, `$parameters`, the failed request as `$method` /
    `$uri`, and Magento's message placeholders and nested validation errors
    interpolated;
  - `TransportException` for connection, timeout and TLS failures;
  - `UnexpectedResponseException` when a successful response isn't what the
    endpoint promises — methods that return an id never invent one.

  Invalid input (a malformed `Configuration`, an invalid store code, an empty
  or `.`/`..` identifier) throws PHP's `InvalidArgumentException` before any
  request is sent.

### Security

- The base URL must be `https://`, since the bearer token is sent with every
  request; `allowInsecureHttp: true` opts out for local development. TLS
  certificates are verified by default.
- Redirects are never followed and a 3xx answer is an error — also for a Guzzle
  client passed to `fromGuzzle()`. Following one would turn a `POST`/`PUT` into
  a `GET`, or replay a request body to another scheme or host.
- Error statuses always raise an `ApiException`, even when a caller-supplied
  Guzzle client sets `http_errors` to `false`.
- Identifiers placed in the URL path are encoded, and an empty one, `.` or `..`
  is refused, so a value can't navigate to a different endpoint. Store codes
  are validated against Magento's own rule.
- The token is trimmed, may not contain control characters, and is redacted
  from `var_dump()` / `print_r()` of a `Configuration`.
- The Guzzle exception is not chained to the SDK's exceptions, so the
  `Authorization` header and request body don't reach logs or error reporters.
  Request bodies and passwords are marked `#[\SensitiveParameter]` to keep them
  out of stack traces (effective on PHP 8.2+).
- Requires `guzzlehttp/guzzle` `^7.15.2`, the first release without the known
  advisories in Guzzle and `guzzlehttp/psr7`.

[Unreleased]: https://github.com/baldie81/magento2sdk/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/baldie81/magento2sdk/releases/tag/v1.0.0
