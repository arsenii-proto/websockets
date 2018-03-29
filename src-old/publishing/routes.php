<?php
use Arsenii\WebSockets\Listener\WebSocketListenerBuilder as Listener;
use Arsenii\WebSockets\Event\WebSocketEvent as Event;
/*
|--------------------------------------------------------------------------
| WebSockets Routes ( Organized by Listener Inteface on message event )
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your WebSocket application.
| These routes are loaded by the App\WebSockets\Kernel within a group which
| is assigned. Enjoy building your API!
|
|--------------------------------------------------------------------------
| use Listener class and register route like Listener::when('pattern','route')
|--------------------------------------------------------------------------
*/


Listener::group('hello = aa', function(){

  Listener::when('l1=:object', "\App\Http\Controllers\FirstCtrl@index");

  Listener::when('aloha&&l1=!object', function(Event $r){
    $r->send('aaa');
  });

});

Listener::build([
  '*' => [
    'pattern' => 'aloha',
    'path'    => function(Event $event){
                    echo "PARSE [{$event->getType()}]\n";
                  }
  ]
]);
