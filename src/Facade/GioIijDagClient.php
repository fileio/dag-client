<?php

namespace Gio\IijDagClient\Facade;

use Illuminate\Support\Facades\Facade;

class GioIijDagClient extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'iij-gio-dag';
    }
}
