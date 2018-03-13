<?php

namespace Arsenii\WebSockets\Facades;


use Illuminate\Support\Facades\Facade;

class WebSocketConnection extends Facade {

    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'WS_Connection';
    }

}