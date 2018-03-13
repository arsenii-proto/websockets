<?php

namespace Arsenii\WebSockets\Command;

use Illuminate\Console\Command;
use Arsenii\WebSockets\Server\WebSocketServer as Server;

class WebSocketsSend extends Command
{

    protected $signature    = 'websockets:send {message=empty} {--host=empty} {--port=empty}';
    protected $description  = 'Command description';

    public function handle()
    {
      $host = $this->option('host') != 'empty' ? $this->option('host') : null;
      $port = $this->option('port') != 'empty' ? $this->option('port') : null;

      $this->websocket  = new Server($host, $port);
      $this->comment("WebSocket send");
      $message = ($this->argument('message') == 'empty') ? '{}' : $this->argument('message');
      $this->websocket->push($message);
      $this->comment("WebSocket sended");
    }
}
