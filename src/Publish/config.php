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

];
