<?php

namespace Arsenii\WebSockets;

use Closure;
use App;

class Emitter
{
    
    /**
     * The array of listenners.
     *
     * @var array
     */
    protected $listeners = [];
    protected $groups    = [];

    protected $types     = [

      'connecting'          => 'onConnecting', //
      'connected'           => 'onConnected', //
      'handshake-sending'   => 'onHandshakeSending', //
      'handshake-sended'    => 'onHandshakeeSended', //
      'message-sending'     => 'onMessageSending', //
      'message-sended'      => 'onMessageSended', //
      'message-received'    => 'onMessageReceived', //
      'disconnecting'       => 'onDisconnecting', //
      'disconnected'        => 'onDisconnected', //
      'backlog'             => 'onBacklog', //
    ];

    public function addListeners(){

        eval( 
            "use \Arsenii\WebSockets\Facades\Emitter;".
            "use \Arsenii\WebSockets\Event; ?>".

            file_get_contents( base_path('routes/websockets.php') ) 
        );

        // include base_path('routes/websockets.php');
    }

    public function dispatch( string $type, Connection $connection = null, string $data = null ){

        if(! array_key_exists( $type, $this->types ) )
            return null;

        $event = new Event( $type, $connection, $data );

        foreach( $this->listeners as $listener ){

            if( method_exists( $listener, $this->types[ $type ] ) ){

                app()->call( [ $listener, $this->types[ $type ] ], [ 'event' => $event ] );
            }
        }

        return $event;
    }    

    public function group( $groups = null, Closure $callback = null ){

        if( !is_null( $callback ) ){

            $this->groups[] = $groups;

            app()->call($callback);

            array_pop( $this->groups );
        }
    }

    public function when( $pattern = null, $path = null ){

        if(
                !is_null( $pattern )
            &&  !is_null( $path ) 
        ){

            $route = [

                'pattern'   => ( 
                                    count( $this->groups )
                                    ? ($this->groups[ count( $this->groups ) -1 ] . '&&')
                                    : ('')
                                ) .$pattern,
                'path'      => $path
            ];

            if(
                    is_null( $route['pattern'] )
                ||  empty( $route['pattern'] ) 
            )
                return false;

            $this->make([ 'message-received' => $route ]);

            return true;
        }

        return false;
    }

    public function build( $routes ){
        
        $this->make( $routes );
    }

    public function push( $listenerClass = null ){

        if(
                !is_null( $listenerClass )
            &&  !is_null( ( $instance = App::make($listenerClass) ) )
        ){
            
            $this->listeners[] =  $instance;
        }
    }

    private function make($routes){

        $this->listeners[] = new Listener( $routes );
    }
    
}