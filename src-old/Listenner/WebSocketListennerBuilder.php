<?php
namespace Arsenii\WebSockets\Listener;

/**
 *
 */
use Closure;
use App;

use Arsenii\WebSockets\Lib\ServerInterface;
use Arsenii\WebSockets\Listener\WebSocketListener;

final class WebSocketListenerBuilder
{

  static private $server;
  static private $groupPattern;

  static public function setServer(ServerInterface $server){
    if( is_null(self::$server) ){
      self::$server = $server;
    }
  }

  static public function group($groupPattern = null, Closure $callback = null){
    if( !is_null( $groupPattern ) && !is_null( $callback ) ){
      self::$groupPattern = $groupPattern;
      app()->call($callback);
      self::$groupPattern = null;
    }
  }

  static public function when($pattern = null, $path = null){
    if( !is_null( $pattern ) && !is_null( $path ) ){

      $route = [
        'pattern' => ( is_null(self::$groupPattern) ? '' : self::$groupPattern . '&&' ) .$pattern,
        'path'    => $path
      ];

      if( is_null( $route['pattern'] ) || empty( $route['pattern'] ) )
        return false;

      self::buildListener([ 'message-received' => $route ]);

      return true;
    }

    return false;
  }

  static public function build($routes){
    self::buildListener( $routes );
  }

  static public function push($listenerClass = null){
    if( !is_null( $listenerClass ) && !is_null( ( $instance = App::make($listenerClass) ) ) ){
      self::$server->addListener( $instance );
    }
  }

  static private function buildListener($routes){
    self::$server->addListener(new WebSocketListener( $routes ));
  }

  private function __construct(){ }

}
