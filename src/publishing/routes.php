<?php
use Arsenii\WebSockets\Listenner\WebSocketListennerBuilder as Listenner;
use Arsenii\WebSockets\Event\WebSocketEvent as Event;
/*
|--------------------------------------------------------------------------
| WebSockets Routes ( Organized by Listenner Inteface on message event )
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your WebSocket application.
| These routes are loaded by the App\WebSockets\Kernel within a group which
| is assigned. Enjoy building your API!
|
|--------------------------------------------------------------------------
| use Listenner class and register route like Listenner::when('pattern','route')
|--------------------------------------------------------------------------
*/


Listenner::group('hello = aa', function(){

  Listenner::when('l1=:object', "\App\Http\Controllers\FirstCtrl@index");

  Listenner::when('aloha&&l1=!object', function(Event $r){
    $r->send('aaa');
  });

});

Listenner::build([
  '*' => [
    'pattern' => 'aloha',
    'path'    => function(Event $event){
                    echo "PARSE [{$event->getType()}]\n";
                  }
  ]
]);
