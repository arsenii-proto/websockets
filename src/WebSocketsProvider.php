<?php

namespace Arsenii\WebSockets\Provider;

use Illuminate\Support\ServiceProvider;

// Commands
use Arsenii\WebSockets\Console\WebSocketsStart;
use Arsenii\WebSockets\Console\WebSocketsSend;

// Services
use Arsenii\WebSockets\Services\ServerService;
use Arsenii\WebSockets\Services\ListenerService;
use Arsenii\WebSockets\Services\EventService;
use Arsenii\WebSockets\Services\StreamService;
use Arsenii\WebSockets\Services\ConnectionService;
use Arsenii\WebSockets\Services\LooperService;
use Arsenii\WebSockets\Services\EmitterService;
use Arsenii\WebSockets\Services\BuilderService;
use Arsenii\WebSockets\Services\DataResolverService;

class WebSocketsProvider extends ServiceProvider
{
    public function boot()
    {
      if ($this->app->runningInConsole()) {
          $this->commands([
              WebSocketsStart::class,
              WebSocketsSend::class
          ]);
      }

      $this->publishes([
            __DIR__.'/../Publish/config.php' => config_path('websockets.php'),
            __DIR__.'/../Publish/routes.php' => base_path('routes/websockets.php'),
        ]);

    }

    public function register()
    {

        $this->app->singleton('WS_Server', function($app) {
            return new ServerService();
        });

        $this->app->singleton('WS_Listener', function($app) {
            return new ListenerService();
        });

        $this->app->singleton('WS_Event', function($app) {
            return new EventService();
        });

        $this->app->singleton('WS_Stream', function($app) {
            return new StreamService();
        });

        $this->app->singleton('WS_Connection', function($app) {
            return new ConnectionService();
        });

        $this->app->singleton('WS_Looper', function($app) {
            return new LooperService();
        });

        $this->app->singleton('WS_Emitter', function($app) {
            return new EmitterService();
        });

        $this->app->singleton('WS_Builder', function($app) {
            return new BuilderService();
        });

        $this->app->singleton('WS_DataResolver', function($app) {
            return new DataResolverService();
        });

    }

    public function provides()
    {
        return [
            'WS_Server',
            'WS_Listener',
            'WS_Event',
            'WS_Stream',
            'WS_Connection',
            'WS_Looper',
            'WS_Emitter',
            'WS_Builder',
            'WS_DataResolver',
        ];
    }
}
