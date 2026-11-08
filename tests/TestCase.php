<?php

namespace JeffersonGoncalves\Centrifugo\Tests;

use JeffersonGoncalves\Centrifugo\CentrifugoServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            CentrifugoServiceProvider::class,
        ];
    }
}
