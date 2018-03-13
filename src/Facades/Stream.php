<?php

namespace Arsenii\WebSockets\Facades;


use Illuminate\Support\Facades\Facade;

class WebSocketStream extends Facade {

    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'WS_Stream';
    }

}