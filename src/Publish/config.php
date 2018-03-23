<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WebSockets Host Address
    |--------------------------------------------------------------------------
    |
    | Here you may provide the host address of the WebSockets server used by your
    | applications. A default option is provided that is compatible with
    | the Apache and Nginx web servers.
    |
    */

    'host' => env('WS_HOST', '0.0.0.0'),

    /*
    |--------------------------------------------------------------------------
    | WebSockets Host Port
    |--------------------------------------------------------------------------
    |
    | Here you may provide the host port of the WebSockets server used by your
    | applications. A default option is provided that is compatible with
    | the Apache and Nginx web servers.
    |
    */

    'port' => env('WS_PORT', '8080'),

    /*
    |--------------------------------------------------------------------------
    | WebSockets Host Path
    |--------------------------------------------------------------------------
    |
    | Here you may provide the host path of the WebSockets server used by your
    | applications. 
    |
    */

    'path' => env('WS_PATH', '/'),

    /*
    |--------------------------------------------------------------------------
    | WebSockets Protocol
    |--------------------------------------------------------------------------
    |
    | Here you may choose the protocol of the WebSockets server used by your
    | applications. A default option is provided that is dinamic and can 
    | receive ws:// and wss:// (with ssl) type of request protocols.
    |
    | Supported: "ws", "wss", "auto"
    |
    */

    'protocol' => env('WS_PROTOCOL', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | WebSockets SSL Protocol
    |--------------------------------------------------------------------------
    |
    | Here you need set the local private key and local certificate for use ssl 
    | protocol in the WebSockets server
    |
    */

    'ssl' => [
        
        'local_private_key' => base_path('/private_key.key'),
        'local_certificate' => base_path('/certificate.pem'),
        
        /*
         * Private key passphrase.
         * When private key was generated witout passphrase
         */
        'passphrase' => 'secret',

    ],

    /*
    |--------------------------------------------------------------------------
    | Websockets Servant
    |--------------------------------------------------------------------------
    |
    | Arsenii Websockets API supports an assortment of back-ends via a single
    | API, giving you convenient access to each back-end using the same
    | syntax for each one. Here you may set the default servant.
    |
    */

    'servant' => env('WS_SERVANT', 'sync'),

    /*
    |--------------------------------------------------------------------------
    | Websockets Servants
    |--------------------------------------------------------------------------
    |
    | Here you may configure the Servant for serve each request received by 
    | your application. A default servant has been added for each 
    | back-end shipped with Laravel.
    |
    | Supported drivers: "sync", "database", "thread"
    |
    */

    'servants' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'daemon' => [
            'driver' => 'database',
            'table' => 'ws_requests',
            'retry_after' => 90,
        ],

        'parallel' => [
            'driver' => 'thread'
        ]

    ]

];
