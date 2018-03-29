<?php

namespace Arsenii\WebSockets;


use \ReflectionFunction;
use \ReflectionMethod;

class Listener
{

  private $triggers = [];

  private $methods = [
    '*',
    'all',
    'connecting',
    'connected',
    'handshake-sending',
    'handshake-sended',
    'message-sending',
    'message-sended',
    'message-received',
    'disconnecting',
    'disconnected',
    'backlog',
  ];

  function __construct( $routes = null){

    if ( is_array( $routes ) ) {

      foreach ($routes as $method => $closure)
        if( in_array( $method, $this->methods ) ){

          if( is_array( $closure ) ){

            if( 
                    isset( $closure['pattern'] )
                && isset( $closure['path'] )
            ){

                $this->triggers[ $method ][] = $closure;

            }else{

              foreach ($closure as $item) {

                if( 
                        isset( $item['pattern'] )
                    && isset( $item['path'] ) 
                ){

                  $this->triggers[ $method ][] = $item;
                }

              }

            }

          }else{

            $this->triggers[ $method ][] = [
            
                'pattern' => '*',
                'path'   => $closure

            ];

          }

        }

    }

  }

  public function onConnecting(Event $event){

    foreach ($this->triggers as $method => $items)
      if(
                $method == 'connecting' 
            ||  $method == 'all' 
            ||  $method == '*' 
        ){

        foreach ($items as $item)
          if( $event->match($item['pattern']) ){

            self::invoke( $item['path'], $event );

          }

      }

  }

  public function onConnected(Event $event){

    foreach ($this->triggers as $method => $items)
      if(
                $method == 'connected' 
            ||  $method == 'all' 
            ||  $method == '*' 
        ){

        foreach ($items as $item)
          if( $event->match($item['pattern']) ){

            self::invoke( $item['path'], $event );

          }

      }

  }


  public function onMessageReceived(Event $event){

    foreach ($this->triggers as $method => $items)
      if(
                $method == 'message-received' 
            ||  $method == 'all' 
            ||  $method == '*' 
        ){

        foreach ($items as $item){
          if( $event->match($item['pattern']) ){
            
            self::invoke( $item['path'], $event );

          }
        }

      }

  }

  public function onMessageSending(Event $event){

    foreach ($this->triggers as $method => $items)
      if(
                $method == 'message-sending' 
            ||  $method == 'all' 
            ||  $method == '*' 
        ){

        foreach ($items as $item)
          if( $event->match($item['pattern']) ){

            self::invoke( $item['path'], $event );

          }

      }

  }

  public function onMessageSended(Event $event){

    foreach ($this->triggers as $method => $items)
      if(
                $method == 'message-sended' 
            ||  $method == 'all' 
            ||  $method == '*' 
        ){

        foreach ($items as $item)
          if( $event->match($item['pattern']) ){

            self::invoke( $item['path'], $event );

          }

      }

  }

  public function onHandshakeSending(Event $event){

    foreach ($this->triggers as $method => $items)
      if(
                $method == 'handshake-sending' 
            ||  $method == 'all' 
            ||  $method == '*' 
        ){

        foreach ($items as $item)
          if( $event->match($item['pattern']) ){

            self::invoke( $item['path'], $event );

          }

      }

  }

  public function onHandshakeeSended(Event $event){

    foreach ($this->triggers as $method => $items)
      if(
                $method == 'handshake-sended' 
            ||  $method == 'all' 
            ||  $method == '*' 
        ){

        foreach ($items as $item)
          if( $event->match($item['pattern']) ){

            self::invoke( $item['path'], $event );

          }

      }

  }


  public function onDisconnecting(Event $event){

    foreach ($this->triggers as $method => $items)
      if(
                $method == 'disconnecting' 
            ||  $method == 'all' 
            ||  $method == '*' 
        ){

        foreach ($items as $item)
          if( $event->match($item['pattern']) ){

            self::invoke( $item['path'], $event );

          }

      }

  }

  public function onDisconnected(Event $event){

    foreach ($this->triggers as $method => $items)
      if(
                $method == 'disconnected' 
            ||  $method == 'all' 
            ||  $method == '*' 
        ){

        foreach ($items as $item)
          if( $event->match($item['pattern']) ){

            self::invoke( $item['path'], $event );

          }

      }

  }

  public function onBacklog(Event $event){

    foreach ($this->triggers as $method => $items)
      if(
                $method == 'backlog' 
            ||  $method == 'all' 
            ||  $method == '*' 
        ){

        foreach ($items as $item)
          if( $event->match($item['pattern']) ){

            self::invoke( $item['path'], $event );

          }

      }

  }

  static public function invoke($path = null, Event $event){

    if( is_string( $path ) ){

      list( $C, $M )    = explode( "@", $path );

      $reflection       = new ReflectionMethod( $C, $M );

    }else{

      $reflection       = new ReflectionFunction( $path );

    }

    $params = [];

    foreach ( $reflection->getParameters() as $P) {

      if(
              $P->getClass()
          &&  $P->getClass()->isInstance( $event )
      ){

          $params[$P->name] = $event;
      }

    }

    app()->call( $path, $params );

  }

}
