<?php

namespace Arsenii\WebSockets\Provider;

use Illuminate\Support\ServiceProvider;
use Arsenii\WebSockets\Command\WebSocketsStart;
use Arsenii\WebSockets\Command\WebSocketsSend;

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
            __DIR__.'/publishing/config.php' => config_path('websockets.php'),
            __DIR__.'/publishing/routes.php' => base_path('routes/websockets.php'),
        ]);

    }

    public function register()
    {

    }
}
