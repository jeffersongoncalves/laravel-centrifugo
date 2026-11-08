<?php

namespace JeffersonGoncalves\Centrifugo;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CentrifugoServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-centrifugo')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations();
    }
}
