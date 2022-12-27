<?php

namespace ElipZis\Cacheable;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Configuring the package.
 *
 * This class is a Package Service Provider
 * More info: https://github.com/spatie/laravel-package-tools
 */
class CacheableServiceProvider extends PackageServiceProvider
{
    /**
     * @param Package $package
     * @return void
     */
    public function configurePackage(Package $package): void
    {
        $package->name('laravel-cacheable-model')->hasConfigFile('cacheable');
    }
}
