<?php

namespace Arsenii\WebSockets\Command;

use Illuminate\Console\Command;
use Arsenii\WebSockets\Server\WebSocketServer as Server;
use Arsenii\WebSockets\Listenner\WebSocketListennerBuilder as Listenner;

class WebSocketsStart extends Command
{

    protected $signature    = 'websockets:start';
    protected $description  = 'Command description';

    public function handle()
    {
      $this->websocket  = new Server();
      Listenner::setServer($this->websocket);
      include base_path('routes/websockets.php');
      $this->comment("WebSocket start [{$this->websocket->address}]");
      $this->websocket->run();
      $this->comment("WebSocket close [{$this->websocket->address}]");
    }
}
