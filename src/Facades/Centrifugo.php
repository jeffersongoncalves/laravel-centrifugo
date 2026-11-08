<?php

namespace JeffersonGoncalves\Centrifugo\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \JeffersonGoncalves\Centrifugo\Centrifugo
 */
class Centrifugo extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-centrifugo';
    }
}
