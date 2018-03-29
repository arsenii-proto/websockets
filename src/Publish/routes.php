<?php
/*
|--------------------------------------------------------------------------
| WebSockets Routes ( Organized by Emitter Inteface on message event )
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your WebSocket application.
| These routes are loaded by the App\WebSockets\Kernel within a group which
| is assigned. Enjoy building your API!
|
|--------------------------------------------------------------------------
| use Emitter class and register route like Emitter::when('pattern','route')
|--------------------------------------------------------------------------
*/


Emitter::group('hello = aa', function(){

  Emitter::when('l1=:object', "\App\Http\Controllers\FirstCtrl@index");

  Emitter::when('aloha&&l1=!object', function(Event $r){
    $r->send('aaa');
  });

});

Emitter::build([
  '*' => [
    'pattern' => 'aloha',
    'path'    => function(Event $event){
                    echo "PARSE [{$event->getType()}]\n";
                  }
  ]
]);
