<?php

namespace Arsenii\WebSockets\Console;

use Illuminate\Console\Command;
use Arsenii\WebSockets\Facades\Server;

class WebSocketsSend extends Command
{

    protected $signature    = 'websockets:send {message=empty} {--host=empty} {--port=empty} {--path=empty} {--type=empty}';
    protected $description  = 'Send Message throw WebSockets ';

    public function handle()
    {

        $host       = $this->option('host') == 'empty' ? null : $this->option('host');
        $port       = $this->option('port') == 'empty' ? null : $this->option('port');
        $path       = $this->option('path') == 'empty' ? null : $this->option('path');
        $type       = $this->option('type') == 'empty' ? null : $this->option('type');
        
        $message    = $this->argument('message') == 'empty' ? null : $this->argument('message');


        Server::instance( $host, $port, $path, $type )->putBacklog( $message );

    }
}
