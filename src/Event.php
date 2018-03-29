<?php

namespace Arsenii\WebSockets;

use \ReflectionFunction;
use \ReflectionMethod;

use Closure;

use Arsenii\WebSockets\Log;

final class Event
{

  private $type;
  private $connection;
  private $stopped;
  private $message;
  private $_data;

  function __construct( string $type = null, Connection $connection = null, $message = null){
    $this->type       = $type;
    $this->connection = $connection;
    $this->message    = $message;
    $this->_data      = DataResolver::parse($message);

    if( is_null( $this->_data ) )
      $this->_data    = [];

  }

  public function getConnection(){

    return $this->connection;
  }

  public function getData(){

    return $this->_data;
  }

  public function getRawData(){

    return $this->message;
  }

  public function get($flow = null, $default = null){

    return DataResolver::get( $this->_data, $flow, $default );
  }

  public function has( $flow = null ){

    return DataResolver::has( $this->_data, $flow );
  }

  public function getType(){

    return $this->type;
  }

  public function stopPropagation(){

    $this->stopped = true;
  }

  public function isPropagationStopped(){

    return is_null($this->stopped) ? !1 : $this->stopped;
  }

  public function match($pattern = null){

    return DataResolver::match( $this->_data, $pattern );    
  }

  public function send($message = null){

    if( in_array( gettype($message), ['object', 'array'] ) ){

      if(
                gettype($message) == 'object'
            && method_exists( $message, 'toString') 
        ){
        
        $message = $message->toString();

      }else{

        $message = json_encode($message);

      }

    }

    $message = (string) $message;

    $this->connection->send($message);

  }

  public function log( string $type = 'info', string $data ){
    
      Log::log( $type, $data );
  }

}
