<?php
namespace Arsenii\WebSockets\Listener;

/**
 *
 */
 use Arsenii\WebSockets\Lib\ListenerInterface;
 use Arsenii\WebSockets\Lib\EventInterface;

class WebSocketListener implements ListenerInterface
{

  private $triggers = [];
  private $methods = [
    '*',
    'all',
    'connecting',
    'connected',
    'message-sending',
    'message-sended',
    'message-received',
    'disconnecting',
    'disconnected'
  ];

  function __construct($routes)
  {
    if ( is_array( $routes ) ) {
      foreach ($routes as $method => $closure)
        if( in_array( $method, $this->methods ) ){
          if( is_array( $closure ) ){
            if( isset( $closure['pattern'] ) && isset( $closure['path'] ) ){
                $this->triggers[ $method ][] = $closure;
            }else{
              foreach ($closure as $item) {
                if( isset( $item['pattern'] ) && isset( $item['path'] ) ){
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

  public function onConnecting(EventInterface $event){
    foreach ($this->triggers as $method => $items)
      if( $method == 'connecting' || $method == 'all' || $method == '*' ){
        foreach ($items as $item)
          if( $event->match($item['pattern']) ){
            $event->invoke($item['path']);
          }
      }
  }

  public function onConnected(EventInterface $event){
    foreach ($this->triggers as $method => $items)
      if( $method == 'connected' || $method == 'all' || $method == '*' ){
        foreach ($items as $item)
          if( $event->match($item['pattern']) ){
            $event->invoke($item['path']);
          }
      }
  }


  public function onMessageReceived(EventInterface $event){
    foreach ($this->triggers as $method => $items)
      if( $method == 'message-received' || $method == 'all' || $method == '*' ){
        foreach ($items as $item)
          if( $event->match($item['pattern']) ){
            $event->invoke($item['path']);
          }
      }
  }


  public function onMessageSending(EventInterface $event){
    foreach ($this->triggers as $method => $items)
      if( $method == 'message-sending' || $method == 'all' || $method == '*' ){
        foreach ($items as $item)
          if( $event->match($item['pattern']) ){
            $event->invoke($item['path']);
          }
      }
  }

  public function onMessageSended(EventInterface $event){
    foreach ($this->triggers as $method => $items)
      if( $method == 'message-sended' || $method == 'all' || $method == '*' ){
        foreach ($items as $item)
          if( $event->match($item['pattern']) ){
            $event->invoke($item['path']);
          }
      }
  }


  public function onDisconnecting(EventInterface $event){
    foreach ($this->triggers as $method => $items)
      if( $method == 'disconnecting' || $method == 'all' || $method == '*' ){
        foreach ($items as $item)
          if( $event->match($item['pattern']) ){
            $event->invoke($item['path']);
          }
      }
  }

  public function onDisconnected(EventInterface $event){
    foreach ($this->triggers as $method => $items)
      if( $method == 'disconnected' || $method == 'all' || $method == '*' ){
        foreach ($items as $item)
          if( $event->match($item['pattern']) ){
            $event->invoke($item['path']);
          }
      }
  }

}
