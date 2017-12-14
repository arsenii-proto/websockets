<?php
namespace Arsenii\WebSockets\Event;

/**
 *
 */

use \ReflectionFunction;
use \ReflectionMethod;

use Closure;
use Arsenii\WebSockets\Lib\EventInterface;
use Arsenii\WebSockets\Connection\WebSocketConnection;

final class WebSocketEvent implements EventInterface
{

  private $type;
  private $connection;
  private $server;
  private $stopped;
  private $_data;

  function __construct($type = null, $connection = null, $message = null, $server = null)
  {
    $this->type       = $type;
    $this->connection = $connection;
    $this->server     = $server;
    $this->_data      = json_decode($message);

    if( is_null( $this->_data ) )
      $this->_data    = [];

  }

  public function get($flow = null, $default = null){
    if( isset( $this->_data->{$flow} ) ){
      return $this->_data->{$flow};
    }else if( !empty( trim( $flow ) ) && ( $parts = explode('.', $flow) ) != null && count( $parts ) > 0 ){
        $last = $this->_data;
        foreach (explode('.', $flow) as $part) {
          if( empty( trim( $part ) ) || is_null( $last ) )
            continue;

          if( isset( $last->{$part} ) ){
              $last = $last->{$part};
          }else{
            return $default;
          }
        }
        return $last;
    }
    return $default;
  }

  public function has($flow = null){
    $val = uniqid();
    return $this->get($flow, $val) != $val;
  }

  public function getType(){
    $types = [
      'Connecting'      => 'connecting',
      'Connected'       => 'connected',
      'MessageSending'  => 'message-sending',
      'MessageSended'   => 'message-sended',
      'MessageReceived' => 'message-received',
      'Disconnecting'   => 'disconnecting',
      'Disconnected'    => 'disconnected'
    ];

    return $types[$this->type];
  }

  public function stopPropagation(){
    $this->stopped = true;
  }

  public function isPropagationStopped(){
    return is_null($this->stopped) ? !1 : $this->stopped;
  }

  public function match($pattern = null){
    $flows = [];
    $match = 1;

    if( is_null( $pattern ) )
      return false;

    if( $pattern === '*' )
      return true;

    foreach (explode('&&', $pattern) as $flow) {
      if( empty( trim( $flow ) ) )
        continue;

      $val = null;
      if( ( $parts = explode('=', $flow) ) != null && count( $parts ) > 1 ){
        if( empty( trim( $parts[0] ) ) )
          continue;

        $flow = trim( $parts[0] );
        $val = trim( $parts[1] );
      }

      if( is_null( $val ) ){
        $flows[] = [ "var" => $flow ];
      }else{
        $flows[] = [
          "var" => $flow,
          "val" => $val
        ];
      }
    }
    foreach ( $flows as $flow) {
      if( $this->has($flow['var']) ){
        if( isset( $flow['val'] ) ){
          $match *= ( $this->matchValue($this->get($flow['var']), $flow['val']) ? 1 : 0);
        }else{
          $match *= 1;
        }
      }else{
        $match = 0;
      }
    }
    return $match != 0;
  }

  public function invoke($path = null){
    if( is_string( $path ) ){
      list( $C, $M ) = explode( "@", $path );
      $reflection = new ReflectionMethod( $C, $M );
    }else{
      $reflection = new ReflectionFunction( $path );
    }
    $params = [];
    foreach ( $reflection->getParameters() as $P) {
      if( $P->getClass() && $P->getClass()->isInstance( $this ) ){
        $params[$P->name] = $this;
      }
    }
    app()->call( $path, $params );
  }

  public function send($message = null){

    if( in_array( gettype($message), ['object', 'array'] ) ){

      if( gettype($message) == 'object' && method_exists( $message, 'toString') ){
        $message = $message->toString();

      }else{
        $message = json_encode($message);

      }

    }

    $message = (string)$message;

    $this->connection->send($message);

  }

  private function matchValue($val, $pattern){
    if(
      ( substr( $pattern, 0, 1 ) === ':' || substr( $pattern, 0, 1 ) === '!' ) &&
      ( in_array( strtolower( substr( $pattern, 1 ) ), [ "null", "num", "number", "numeric", "int", "integer", "float", "bool", "boolean", "string", "array", "object" ] ) )
    ){
        switch( substr( $pattern, 1 ) ){
          case "null":
              return ( substr( $pattern, 0, 1 ) == ":" ) ? is_null( $val ) : ! is_null( $val );
          case "num":
          case "number":
          case "numeric":
              return ( substr( $pattern, 0, 1 ) == ":" ) ? is_numeric( $val ) : ! is_numeric( $val );
          case "int":
          case "integer":
              return ( substr( $pattern, 0, 1 ) == ":" ) ? is_int( $val ) : ! is_int( $val );
          case "float":
              return ( substr( $pattern, 0, 1 ) == ":" ) ? is_float( $val ) : ! is_float( $val );
          case "bool":
          case "boolean":
              return ( substr( $pattern, 0, 1 ) == ":" ) ? is_bool( $val ) : ! is_bool( $val );
          case "string":
              return ( substr( $pattern, 0, 1 ) == ":" ) ? is_string( $val ) : ! is_string( $val );
          case "array":
              return ( substr( $pattern, 0, 1 ) == ":" ) ? is_array( $val ) : ! is_array( $val );
          case "object":
              return ( substr( $pattern, 0, 1 ) == ":" ) ? is_object( $val ) : ! is_object( $val );
        }
    }
    return $val == $pattern;
  }

}
