# Automatically cache Laravel Eloquent models by queries

[![Latest Version on Packagist](https://img.shields.io/packagist/v/elipzis/laravel-cacheable-model.svg?style=flat-square)](https://packagist.org/packages/elipzis/laravel-cacheable-model)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/elipzis/laravel-cacheable-model/run-tests.yml?branch=main)](https://github.com/elipzis/laravel-cacheable-model/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/elipzis/laravel-cacheable-model/php-cs-fixer.yml?branch=main)](https://github.com/elipzis/laravel-cacheable-model/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/elipzis/laravel-cacheable-model.svg?style=flat-square)](https://packagist.org/packages/elipzis/laravel-cacheable-model)

Easy and automatic select-query caching for your Eloquent models!

* Get cached query results and reduce your database load automatically
* Configure TTL, prefixes, unique-queries etc.
* No manual cache calls needed
* Automated cache flush in case of updates, inserts or deletions

You can make any Eloquent model cacheable by adding the trait

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

You can publish the config file with:

```bash
php artisan vendor:publish --tag="cacheable-model-config"
```

This is the contents of the published config file:

```php
    //Default values for the Cacheable trait - Can be overridden per model
    return [
        //How long should cache last in general?
        'ttl' => 300,
        //By what should cache entries be prefixed?
        'prefix' => 'cacheable',
        //What is the identifying, unique column name?
        'identifier' => 'id',
        //Do you need logging?
        'logging' => [
            'enabled' => false,
            'level' => 'debug',
        ],
    ];
```

## Usage

Make your model cacheable by adding the trait:

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

The package overrides the QueryBuilder and scans for the same queries to capture and return the cached values.

You do not need to do anything else but just use your model as you would and leverage the power of cached entries! 

### Configuration

The following configuration can be overridden per model

```php
public function getCacheableProperties(): array {
    return [
        'ttl' => 300,
        'prefix' => 'cacheable',
        'identifier' => 'id',
        'logging' => [
            'enabled' => false,
            'level' => 'debug',
        ],
    ];
}
```

### Disable cache

Depending on your cache and database performance, you might like to retrieve a query without caching sometimes:

```php
YourModel::query()->withoutCache()->get();
```

### Flush cache

If your data is updated outside of this package, you can flush it yourself by calling:

```php
YourModel::query()->flushCache();
```

### Note on using caching

This package overrides the native QueryBuilder and is capturing every database query, therefore it imposes a load and
performance burden.

If you use caching intensively on a model, this package and its use can help. If an entity is permanently changing, it
won't make sense to make it `Cacheable`.

It is recommended to only make models `Cacheable` which have a reasonable caching time in your system. Do not use the
trait on any other or all models out of the box, but think about where it makes sense.

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
