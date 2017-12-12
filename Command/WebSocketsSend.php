<?php

namespace Arsenii\WebSockets\Command;

use Illuminate\Console\Command;
use Arsenii\WebSockets\Server\WebSocketServer as Server;

class WebSocketsSend extends Command
{

    protected $signature    = 'websockets:send {message=empty}';
    protected $description  = 'Command description';

    public function handle()
    {
      $this->websocket  = new Server();
      $this->comment("WebSocket send");
      $message = ($this->argument('message') == 'empty') ? '{}' : $this->argument('message');
      $this->websocket->push($message);
      $this->comment("WebSocket sended");
    }
}
