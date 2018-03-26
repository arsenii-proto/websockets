<?php

namespace Arsenii\WebSockets;

use Illuminate\Support\ServiceProvider;

// Commands
use Arsenii\WebSockets\Console\WebSocketsStart;
use Arsenii\WebSockets\Console\WebSocketsSend;
use Arsenii\WebSockets\Console\WebSocketsDaemon;


class WebSocketsProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                WebSocketsStart::class,
                WebSocketsSend::class,
                WebSocketsDaemon::class,
            ]);
        }

        $this->publishes([
            __DIR__.'/Publish/config.php' => config_path('websockets.php'),
            __DIR__.'/Publish/routes.php' => base_path('routes/websockets.php'),
        ]);

    }

    public function register()
    {

        $this->app->singleton('ws.stream', function($app) {
            return new \Arsenii\WebSockets\Stream();
        });
        
        $this->app->singleton('ws.looper', function($app) {
            return new \Arsenii\WebSockets\Looper();
        });
        
        // $this->app->singleton('ws.emitter', function($app) {
        //     return new \Arsenii\WebSockets\Emitter();
        // });

        $this->app->singleton('ws.builder', function($app) {
            return new \Arsenii\WebSockets\Builder();
        });

        $this->app->singleton('ws.dataResolver', function($app) {
            return new \Arsenii\WebSockets\DataResolver();
        });

        $this->app->singleton('ws.servant', function($app) {
            return new \Arsenii\WebSockets\Servant();
        });


        $this->app->bind('ws.server', function($app) {
            return new \Arsenii\WebSockets\Server();
        });

        $this->app->bind('ws.listener', function($app) {
            return new \Arsenii\WebSockets\Listener();
        });

        $this->app->bind('ws.event', function($app) {
            return new \Arsenii\WebSockets\Event();
        });

        $this->app->bind('ws.connection', function($app) {
            return new \Arsenii\WebSockets\Connection();
        });


    }
}
