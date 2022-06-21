# Query-based cacheable models on-the-fly for your Laravel app

[![Latest Version on Packagist](https://img.shields.io/packagist/v/elipzis/laravel-cacheable-model.svg?style=flat-square)](https://packagist.org/packages/elipzis/laravel-cacheable-model)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/elipzis/laravel-cacheable-model/run-tests?label=tests)](https://github.com/elipzis/laravel-cacheable-model/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/elipzis/laravel-cacheable-model/Check%20&%20fix%20styling?label=code%20style)](https://github.com/elipzis/laravel-cacheable-model/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/elipzis/laravel-cacheable-model.svg?style=flat-square)](https://packagist.org/packages/elipzis/laravel-cacheable-model)

Easy select-query caching for your models!

* Get cached query results and reduce your database load
* Configure TTL, prefixes, unique-queries etc.
* Automated cache flush in case of updates, inserts or deletions

Make any model cachable by adding the trait

```php
...
use ElipZis\Cacheable\Models\Traits\Cacheable;
...

class YourModel extends Model {

    use Cacheable;
    ... 
```

and leverage the power of a Redis, memcached or other caches.

## Installation

You can install the package via composer:

```bash
composer require elipzis/laravel-cacheable-model
```

## Usage

Make your model cacheable

```php
...
use ElipZis\Cacheable\Models\Traits\Cacheable;
...

class YourModel extends Model {

    use Cacheable;
    ... 
```

and then just use your normal model query, for example

```php
YourModel::query()->get();
```

```php
YourModel::query()->where('field', 'test')->first();
```

```php
YourModel::query()->insert([...]);
```

### Configuration

The following configuration can be overridden per model

```php
public function getCacheableProperties(): array {
    return [
        'ttl'        => 300,
        'prefix'     => 'cacheable',
        'identifier' => 'id',
        'logLevel'   => 'error'
    ];
}
```

### Disable cache

Depending on your cache and database performance, you might like to retrieve a query without caching sometimes:

```php
YourModel::query()->withoutCache()->get();
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [elipZis GmbH](https://elipZis.com)
- [NeA](https://github.com/nea)
- [All Contributors](https://github.com/elipZis/laravel-cacheable-model/contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
