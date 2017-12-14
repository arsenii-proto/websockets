<?php

namespace Arsenii\WebSockets\Command;

use Illuminate\Console\Command;
use Arsenii\WebSockets\Server\WebSocketServer as Server;
use Arsenii\WebSockets\Listenner\WebSocketListennerBuilder as Listenner;

class WebSocketsStart extends Command
{

    protected $signature    = 'websockets:start {--host=empty} {--port=empty}';
    protected $description  = 'Command description';

    public function handle()
    {
      $host = $this->option('host') != 'empty' ? $this->option('host') : null;
      $port = $this->option('port') != 'empty' ? $this->option('port') : null;

      $this->websocket  = new Server($host, $port);
      Listenner::setServer($this->websocket);
      include base_path('routes/websockets.php');
      $this->comment("WebSocket start [{$this->websocket->address}]");
      $this->websocket->run();
      $this->comment("WebSocket close [{$this->websocket->address}]");
    }
}
