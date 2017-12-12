<?php
namespace Arsenii\WebSockets\Listenner;

/**
 *
 */
use Closure;
use App;

use Arsenii\WebSockets\Lib\ServerInterface;
use Arsenii\WebSockets\Listenner\WebSocketListenner;

final class WebSocketListennerBuilder
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

      self::buildListenner([ 'message-received' => $route ]);

      return true;
    }

    return false;
  }

  static public function build($routes){
    self::buildListenner( $routes );
  }

  static public function push($listennerClass = null){
    if( !is_null( $listennerClass ) && !is_null( ( $instance = App::make($listennerClass) ) ) ){
      self::$server->addListenner( $instance );
    }
  }

  static private function buildListenner($routes){
    self::$server->addListenner(new WebSocketListenner( $routes ));
  }

  private function __construct(){ }

}
