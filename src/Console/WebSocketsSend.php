<?php

namespace Arsenii\WebSockets\Console;

use Illuminate\Console\Command;

class WebSocketsSend extends Command
{

    protected $signature    = 'websockets:send {message=empty} {--host=empty} {--port=empty}';
    protected $description  = 'Send Message throw WebSockets ';

    public function handle()
    {

    }
}
