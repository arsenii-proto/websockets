<?php

namespace Arsenii\WebSockets\Console;

use Illuminate\Console\Command;
use Arsenii\WebSockets\Facades\Server;

class WebSocketsStart extends Command
{

    protected $signature    = 'websockets:start {--host=empty} {--port=empty}';
    protected $description  = 'Start WebSockets';

    public function handle()
    {
        Server::start();
    }
}
