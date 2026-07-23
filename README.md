# ARES PHP Client

A small, strongly typed PHP client for searching and retrieving economic
subjects from the Czech ARES registry.

The package keeps the public API focused: validated query objects go in,
immutable DTOs come out, and transport concerns stay behind PSR interfaces.
It has no dependency on CakePHP or any other framework but fits naturally
into a dependency-injection container.

- Retrieve an economic subject by its eight-digit company registration number.
- Search by registration number, business name, address, legal form, tax office, or CZ-NACE code.
- Navigate typed, result-window-aware pagination.
- Use any PSR-18 HTTP client and PSR-17 request/stream factories.
- Handle validation, transport, API, rate-limit, and mapping failures explicitly.

> [!NOTE]
> This is an independent package. It is not affiliated with or
> endorsed by the Czech Ministry of Finance. Data and service availability are
> controlled by the official [ARES registry](https://www.ares.gov.cz/).

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Retrieve a company](#retrieve-a-company)
- [Search for companies](#search-for-companies)
- [Pagination](#pagination)
- [Working with results](#working-with-results)
- [ARES ID and registration number](#ares-id-and-registration-number)
- [Configuration](#configuration)
- [Error handling](#error-handling)
- [CakePHP 5 integration](#cakephp-5-integration)
- [Demo](#demo)
- [Development](#development)
- [Design and scope](#design-and-scope)
- [License](#license)

## Requirements

| Dependency            | Version                           |
|-----------------------|-----------------------------------|
| PHP                   | `^8.5`                            |
| JSON extension        | `ext-json`                        |
| PSR-18 HTTP client    | `psr/http-client:^1.0`            |
| PSR-17 HTTP factories | `psr/http-factory:^1.0`           |
| PSR-7 HTTP messages   | `psr/http-message:^1.1 \|\| ^2.0` |

The package depends on HTTP interfaces, not on a concrete HTTP implementation.
Your application therefore remains in control of its HTTP client, timeouts,
proxy configuration, logging, and retry policy.

## Installation

Until the first tagged release is published through Packagist, install the
current development branch directly from GitHub:

```bash
composer config repositories.ares-php-client vcs https://github.com/jarovarga/ares-php-client
composer require jarovarga/ares-php-client:dev-main guzzlehttp/guzzle
```

After a tagged Packagist release is available, installation will reduce to:

```bash
composer require jarovarga/ares-php-client guzzlehttp/guzzle
```

These examples use Guzzle as both the PSR-18 client and the provider of PSR-17 factories.
Guzzle is not required by the library itself. You may use any combination that provides:

- `Psr\Http\Client\ClientInterface`
- `Psr\Http\Message\RequestFactoryInterface`
- `Psr\Http\Message\StreamFactoryInterface`

There is deliberately no HTTP-client auto-discovery. Dependencies are passed
to `ClientFactory` explicitly, which keeps application wiring predictable and
easy to replace in tests.

## Quick start

```php
<?php

declare(strict_types=1);

use AresApi\ClientFactory;
use AresApi\Configuration;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;

require __DIR__ . '/vendor/autoload.php';

$httpFactory = new HttpFactory();

$client = (new ClientFactory(
    new GuzzleClient(),
    $httpFactory,
    $httpFactory,
))->create(new Configuration(
    userAgent: 'my-application/1.0',
));
```

The same `$client` instance can be shared and injected wherever the application needs ARES data:

```php
$companies = $client->companies();
```

Use an application-specific user agent in production. It makes requests from
your application identifiable without coupling the library to your framework.

## Retrieve a company

Exact lookup accepts a validated `CompanyRegistrationNumber`:

```php
use AresApi\ValueObject\CompanyRegistrationNumber;

$company = $client->companies()->get(
    new CompanyRegistrationNumber('27074358'),
);

printf(
    "%s\n%s\n",
    $company->businessName() ?? 'Business name unavailable',
    $company->registeredOffice()?->formattedAddress()
        ?? 'Registered office unavailable',
);
```

A company registration number:

- contains exactly eight ASCII digits;
- preserves leading zeroes;
- is never silently trimmed or coerced;
- can be read through `value()` or cast to `string`.

Invalid input fails immediately with `ValidationException`, before an HTTP request is made.

## Search for companies

At least one criterion must be supplied:

```php
use AresApi\Company\Query\CompanySearchQuery;
use AresApi\Pagination\PageRequest;

$result = $client->companies()->search(
    new CompanySearchQuery(
        businessName: 'Asseco',
        addressText: 'Praha',
        page: new PageRequest(number: 1, size: 20),
    ),
);

foreach ($result as $company) {
    printf(
        "%s | %s\n",
        $company->registrationNumber()?->value()
            ?? 'registration number unavailable',
        $company->businessName() ?? 'business name unavailable',
    );
}
```

Search criteria are combined into one ARES query. Supported criteria are:

| Argument              | Type                              | Validation                                       |
|-----------------------|-----------------------------------|--------------------------------------------------|
| `registrationNumbers` | `list<CompanyRegistrationNumber>` | At most 100 unique values                        |
| `businessName`        | `?string`                         | Non-empty UTF-8 text, maximum 2,000 characters   |
| `addressText`         | `?string`                         | Non-empty UTF-8 text, maximum 1,500 characters   |
| `legalFormCodes`      | `list<string>`                    | Each code contains exactly three digits          |
| `rosLegalFormCodes`   | `list<string>`                    | Each code contains exactly three digits          |
| `taxOfficeCodes`      | `list<string>`                    | Each code contains exactly three digits          |
| `czNaceCodes`         | `list<string>`                    | Each code contains 1–5 non-whitespace characters |
| `page`                | `PageRequest`                     | Defaults to page 1 with 20 items                 |

A complete query can use all supported filters:

```php
use AresApi\Company\Query\CompanySearchQuery;
use AresApi\Pagination\PageRequest;
use AresApi\ValueObject\CompanyRegistrationNumber;

$query = new CompanySearchQuery(
    registrationNumbers: [
        new CompanyRegistrationNumber('27074358'),
    ],
    businessName: 'Asseco',
    addressText: 'Praha',
    legalFormCodes: ['121'],
    rosLegalFormCodes: ['121'],
    taxOfficeCodes: ['004'],
    czNaceCodes: ['620'],
    page: new PageRequest(number: 1, size: 25),
);

$result = $client->companies()->search($query);
```

Text criteria are trimmed. Lists are deduplicated while preserving their first-seen order.

## Pagination

Pagination is one-based:

```php
use AresApi\Pagination\PageRequest;

$page = new PageRequest(number: 2, size: 25);

$page->number(); // 2
$page->size();   // 25
$page->offset(); // 25
```

ARES exposes at most the first 10,000 items of a result set. `PageRequest`
validates that the requested page fits inside this window:

- page number must be at least `1`;
- page size must be between `1` and `10_000`;
- the requested page may not extend beyond the 10,000-item result window.

Pagination metadata is returned with every search:

```php
$pageInfo = $result->pageInfo();

$pageInfo->currentPage();
$pageInfo->pageSize();
$pageInfo->totalItems();
$pageInfo->totalPages();
$pageInfo->hasPreviousPage();
$pageInfo->hasNextPage();
```

`totalPages()` describes the complete match count reported by ARES and can
therefore exceed the accessible result window. Use `hasNextPage()` when
building navigation; it accounts for both the match count and the 10,000-item limit.

## Working with results

`CompanySearchResult` is both `Countable` and iterable:

```php
if ($result->isEmpty()) {
    echo 'No matching companies.';
}

echo count($result); // Items on this page
echo $result->count(); // Items on this page
echo $result->pageInfo()->totalItems(); // All matches reported by ARES

foreach ($result as $company) {
    // $company is an AresApi\Company\DTO\Company
}

$items = $result->items(); // list<Company>
```

The main company accessors are:

| Group      | Accessors                                                                        |
|------------|----------------------------------------------------------------------------------|
| Identity   | `aresId()`, `registrationNumber()`, `businessName()`                             |
| Registry   | `legalForm()`, `taxIdentificationNumber()`, `taxOfficeCode()`, `primarySource()` |
| Lifecycle  | `establishedOn()`, `dissolvedOn()`, `updatedOn()`, `hasDissolutionDate()`        |
| Location   | `registeredOffice()`                                                             |
| Activities | `czNaceCodes()`, `czNace2008Codes()`                                             |

Company, address, legal-form, pagination, and result DTOs are immutable.
Most company fields are nullable because ARES does not guarantee that every
source register supplies every value.

```php
$address = $company->registeredOffice();

$formattedAddress = $address?->formattedAddress();
$postalCode = $address?->postalCodeText();
$municipality = $address?->municipalityName();
$countryCode = $address?->countryCode();

$legalFormCode = $company->legalForm()?->code()
    ?? $company->legalForm()?->rosCode();

$establishedOn = $company->establishedOn()?->format('Y-m-d');
```

Dates are represented as `DateTimeImmutable` values at midnight UTC.
Activity-code collections are returned as deduplicated lists of strings.

## ARES ID and registration number

> [!IMPORTANT]
> `aresId()` and `registrationNumber()` are not interchangeable.

Every mapped company has an ARES record identifier:

```php
$company->aresId(); // "27074358" or, for example, "ARES_00360478"
```

A Czech company registration number is a separate, optional value:

```php
$registrationNumber = $company->registrationNumber()?->value();
```

For many Czech subjects, both identifiers contain the same eight digits. Some
records—particularly records originating from other source registers—only
have an internal prefixed identifier such as `ARES_00360478`. For those
records, `registrationNumber()` correctly returns `null`.

Never display `aresId()` as a fallback registration number:

```php
// Correct
echo $company->registrationNumber()?->value() ?? 'Not provided by ARES';

// Incorrect: a prefixed internal ARES ID is not a registration number
echo $company->registrationNumber()?->value() ?? $company->aresId();
```

The exact `get()` operation accepts only `CompanyRegistrationNumber`.
Prefixed ARES IDs can appear in search results but cannot be passed to `get()`.

## Configuration

`Configuration` controls the base URI, user agent, and additional request headers:

```php
use AresApi\ClientFactory;
use AresApi\Configuration;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;

$configuration = new Configuration(
    baseUri: Configuration::DEFAULT_BASE_URI,
    userAgent: 'my-application/1.0',
    headers: [
        'X-Correlation-ID' => 'request-123',
    ],
);

$httpClient = new GuzzleClient([
    'connect_timeout' => 5.0,
    'timeout' => 15.0,
]);
$httpFactory = new HttpFactory();

$client = (new ClientFactory(
    $httpClient,
    $httpFactory,
    $httpFactory,
))->create($configuration);
```

Defaults:

| Option             | Default                                             |
|--------------------|-----------------------------------------------------|
| Base URI           | `https://ares.gov.cz/ekonomicke-subjekty-v-be/rest` |
| User agent         | `ares-php-client`                                   |
| Additional headers | `[]`                                                |

The base URI must be an absolute HTTP or HTTPS URL without a query or fragment.
Changing it is mainly useful for a test server, proxy, or compatible API.

The client owns protocol-sensitive headers and rejects attempts to override them through `Configuration`:

- `Accept`
- `Content-Length`
- `Content-Type`
- `Host`
- `Transfer-Encoding`
- `User-Agent`

Header names are checked case-insensitively. Configure the user agent through
the dedicated `userAgent` argument.

Timeouts and other transport options belong to the injected HTTP client, as
shown in the configuration example above.

## Error handling

All package exceptions extend `AresApi\Exception\AresException`:

```text
RuntimeException
└── AresException
    ├── ValidationException
    ├── RequestException
    ├── TransportException
    ├── InvalidResponseException
    └── ApiException
        ├── NotFoundException
        └── RateLimitException
```

| Exception                  | Meaning                                                                    |
|----------------------------|----------------------------------------------------------------------------|
| `ValidationException`      | Invalid configuration, identifier, query, page, or caller-created DTO      |
| `RequestException`         | The PSR request could not be created or its JSON body could not be encoded |
| `TransportException`       | The PSR-18 client could not complete the request                           |
| `InvalidResponseException` | ARES returned invalid JSON or an unexpected response structure             |
| `NotFoundException`        | ARES returned HTTP 404                                                     |
| `RateLimitException`       | ARES returned HTTP 429                                                     |
| `ApiException`             | ARES returned another non-successful HTTP response                         |

Catch the most specific failures first:

```php
use AresApi\Exception\ApiException;
use AresApi\Exception\AresException;
use AresApi\Exception\NotFoundException;
use AresApi\Exception\RateLimitException;

try {
    $company = $client->companies()->get($registrationNumber);
} catch (RateLimitException $exception) {
    $retryAfter = $exception->retryAfterSeconds();

    // Schedule a retry according to your application's policy.
} catch (NotFoundException $exception) {
    // No company exists for the requested registration number.
} catch (ApiException $exception) {
    $statusCode = $exception->statusCode();
    $apiCode = $exception->apiCode();
    $apiSubCode = $exception->apiSubCode();
} catch (AresException $exception) {
    // Validation, request, transport, or response-mapping failure.
}
```

`ApiException` also exposes `responseBody()`. `InvalidResponseException` may
expose the raw body for decoding failures; it can be `null` for structural
mapping failures. Treat response bodies as diagnostic data: they can be large
or contain information you do not want to show directly to an end user.

The package does not retry requests automatically. This avoids hidden duplicate
traffic and lets the application choose an appropriate retry and backoff strategy.

## CakePHP 5 integration

Register one shared client in `src/Application.php`. CakePHP can then inject the
interface into controller actions. Commands and application services can use
the same shared instance when their container definitions declare it as an argument.

```php
use AresApi\ClientFactory;
use AresApi\ClientInterface as AresClientInterface;
use AresApi\Configuration;
use Cake\Core\ContainerInterface;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;

public function services(ContainerInterface $container): void
{
    $container->addShared(
        AresClientInterface::class,
        static function (): AresClientInterface {
            $httpFactory = new HttpFactory();

            return (new ClientFactory(
                new GuzzleClient([
                    'connect_timeout' => 5.0,
                    'timeout' => 15.0,
                ]),
                $httpFactory,
                $httpFactory,
            ))->create(new Configuration(
                userAgent: 'my-cakephp-application/1.0',
            ));
        },
    );
}
```

Inject the abstraction, not the concrete client:

```php
use AresApi\ClientInterface;
use AresApi\Company\Query\CompanySearchQuery;

public function search(ClientInterface $ares): void
{
    $result = $ares->companies()->search(
        new CompanySearchQuery(businessName: 'Asseco'),
    );

    $this->set(compact('result'));
}
```

This keeps controller code independent of transport construction and makes
the ARES boundary straightforward to replace in application tests.

See CakePHP's official [dependency-injection documentation](https://book.cakephp.org/5.x/development/dependency-injection.html) for other container wiring options.

## Demo

The repository includes a self-contained web interface in
[`demo.php`](demo.php). It searches by business name or eight-digit company
registration number, renders result cards, and provides pagination. Its CSS is
inline, and it uses no JavaScript or external assets.

Install the development dependencies and start PHP's built-in web server from the repository root:

```bash
composer install
php -S 127.0.0.1:8080
```

Then open:

```text
http://127.0.0.1:8080/demo.php
```

The demo sends real requests to the public ARES service. Do not use PHP's
built-in server as a production web server.

## Development

Install dependencies:

```bash
composer install
```

Run the complete quality gate:

```bash
composer check
```

The command validates `composer.json`, checks PSR-12 formatting for the library
source and tests, and runs the test suite. Individual commands are also available:

```bash
composer composer:validate
composer cs
composer cs:fix
composer test
```

Tests use deterministic fixtures and test doubles; they do not require the live ARES service.

## Design and scope

The library is intentionally narrow:

- `ClientInterface` is the application entry point.
- `CompanyResourceInterface` owns company detail and search operations.
- Query and value objects validate caller input before transport.
- PSR-18 and PSR-17 keep infrastructure replaceable.
- Mappers isolate the external response schema from public DTOs.
- Immutable DTOs prevent partially mutated API state.
- Domain-specific exceptions make failures explicit.

Currently supported operations:

| Operation                                           | Client method                       |
|-----------------------------------------------------|-------------------------------------|
| Retrieve an economic subject by registration number | `$client->companies()->get(...)`    |
| Search economic subjects with pagination            | `$client->companies()->search(...)` |

The package currently does not provide:

- automatic retries or backoff;
- caching;
- HTTP-client auto-discovery;
- a raw response payload accessor;
- source-register-specific ARES endpoints.

For the upstream schema and the full set of public ARES endpoints, consult the official [ARES REST API documentation](https://ares.gov.cz/swagger-ui/).

## License

ARES PHP Client is released under the [ISC License](LICENSE.md).