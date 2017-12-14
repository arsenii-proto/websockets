<?php
namespace Arsenii\WebSockets\Server;

/**
 *
 */
use Exception;

use Arsenii\WebSockets\Lib\ServerInterface;
use Arsenii\WebSockets\Lib\ConnectionInterface;
use Arsenii\WebSockets\Lib\ListennerInterface;

use Arsenii\WebSockets\Connection\WebSocketConnection;
use Arsenii\WebSockets\Event\WebSocketEvent;

final class WebSocketServer implements ServerInterface
{

  private $is_running;
  private $host;
  private $port;
	private $socket;
  private $sockets;
  private $connections = [];
  private $listenners = [];

  private $inlinePort;
  private $inlineSocket;
  private $inlineSockets;
  private $inlineConnections = [];

  public $address;

  function __construct($host = null, $port = null, $inlinePort = null)
  {
      $this->is_running   = false;
      $this->host         = is_null($host)        ? config('websockets.host')       : $host;
      $this->port         = is_null($port)        ? config('websockets.port')       : $port;
      $this->inlinePort   = is_null($inlinePort)  ? config('websockets.inlinePort') : $inlinePort;
      $this->address      = "ws://{$this->host}:{$this->port}";
  }

  public function run(){
    set_time_limit(0);
		ob_implicit_flush();
    $this->is_running = true;
    $maxConnections   = SOMAXCONN;

		$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

		if ($this->socket === false) {
      $this->error('Creating socket failed: ');
		}

		$this->sockets[] = $this->socket;

		if ( socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1) === false ) {
      $this->error('Setting socket option to reuse address to true failed: ');
		}

		if (socket_bind($this->socket, $this->host, $this->port) === false) {
      $this->error('Binding to port '.$this->port.' on host "'.$this->host.'" failed: ');
		}

		if (socket_listen($this->socket, $maxConnections) === false) {
      $this->error('Starting to listen on the socket on port '.$this->port.' and host "'.$this->host.'" failed: ');
		}

    if( !is_null( $this->inlinePort ) ){
    		$this->inlineSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

    		if ($this->inlineSocket === false) {
          $this->error('Creating Inline socket failed: ');
    		}

        $this->inlineSockets[] = $this->inlineSocket;

    		if ( socket_set_option($this->inlineSocket, SOL_SOCKET, SO_REUSEADDR, 1) === false ) {
          $this->error('Setting Inline socket option to reuse address to true failed: ');
    		}

    		if (socket_bind($this->inlineSocket, $this->host, $this->inlinePort) === false) {
          $this->error('Binding to port '.$this->inlinePort.' on host "'.$this->host.'" failed: ');
    		}

    		if (socket_listen($this->inlineSocket, 20) === false) {
          $this->error('Starting to listen on the Inline socket on port '.$this->inlinePort.' and host "'.$this->host.'" failed: ');
    		}
    }

    while (true) {
      $this->listen();
      if( !is_null( $this->inlinePort ) ){
        $this->listen(false);
      }
    }
  }

  private function listen( $trigger_events = true ){
    $resetSelect        = 0.001;
    $sockets            = $trigger_events ? $this->sockets : $this->inlineSockets;
    $write              = [];
    $except             = [];
    $this->is_running   = true;
		$result             = socket_select( $sockets, $write, $except, $resetSelect, $resetSelect );

    // echo 'AFTER_SELECT'.PHP_EOL;

		if ($result === false) {
			socket_close(($trigger_events ? $this->socket : $this->inlineSocket));
      $this->error('Checking for changed sockets failed: ');
		}

		foreach ($sockets as $socket) {
			if ($socket == ($trigger_events ? $this->socket : $this->inlineSocket)) {
				$newSocket = socket_accept(($trigger_events ? $this->socket : $this->inlineSocket));

				if ($newSocket !== false){
            $this->newConnection($newSocket, $trigger_events);
				} else {
          $this->error('Failed to accept incoming client: ');
				}

			} else {
				$connection = $this->getConnection($socket, $trigger_events);
				if (is_null($connection)) {
          $this->error('Failed to match given socket to client - '.($trigger_events ? 'T' : 'F'));
					socket_close($socket);
					continue;
				}

				$buffer = '';
				$message = '';

				$bytes = @socket_recv($socket, $buffer, 4096, 0);

				if ($bytes === false) {
          $this->error('Failed to receive data from client #'.$connection->id.': ');
          continue;
				}

				$len = ord($buffer[1]) & 127;

				$masks = null;
				$data = null;

				if ($len === 126) {
					$masks = substr($buffer, 4, 4);
					$data = substr($buffer, 8);
				} else if ($len === 127) {
					$masks = substr($buffer, 10, 4);
					$data = substr($buffer, 14);
				} else {
					$masks = substr($buffer, 2, 4);
					$data = substr($buffer, 6);
				}

				for ($index = 0; $index < strlen($data); $index++) {
					$message .= $data[$index] ^ $masks[$index % 4];
				}

				if ($bytes == 0) {
					$this->lostConnection($socket, $trigger_events);
				} else {
					if ($connection->state == WebSocketConnection::STATE_OPEN) {
						$this->newMessage($connection, $message, $trigger_events);
					} else if ($connection->state == WebSocketConnection::STATE_CONNECTING) {
            $this->acceptConnection($connection, $buffer, $trigger_events);
					}
				}
			}
		}
  }

  public function push($message = null, ConnectionInterface $conn = null){

    if( !is_null($message) ){

        if( $this->is_running){

          if( !is_null($conn) && $conn instanceof  ConnectionInterface ){
            $this->send($conn, $message);
          }else{

            foreach ($this->connections as $conn) {
              $this->send($conn, $message);
            }

          }

        }else{

          if( in_array( gettype($message), ['object', 'array'] ) ){

            if( gettype($message) == 'object' && method_exists( $message, 'toString') ){
              $message = $message->toString();

            }else{
              $message = json_encode($message);

            }

          }

          $message = (string)$message;

          $sock = fsockopen($this->host, $this->inlinePort, $errno, $errstr, 2);
      		fwrite($sock, "GET / HTTP/1.1\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nHost: ".$this->host."\r\nSec-WebSocket-Key: TyPfhFqWTjuw8eDAxdY8xg==\r\nSec-WebSocket-Version: 13\r\nContent-Length: ".strlen($message)."\r\n\r\n");
      		$headers = fread($sock, 2000);
          fwrite($sock, $this->hybi10Encode($message));
          fclose($sock);

        }

    }

  }

  public function addListenner(ListennerInterface $listenner = null){
    if( !is_null($listenner) ) $this->listenners[] = $listenner;
  }

  public function removeListenner(ListennerInterface $listenner = null){
    if( !is_null($listenner) )
      foreach ($this->listenners as $i => $ln)
        if( $this->listenners[$i] == $listenner )
          unset($this->listenners[$i]);
  }

  public function getConnections(){
    return $this->connections;
  }

  public function removeConnection(ConnectionInterface $conn = null){
    if( !is_null($conn) )
      foreach ($this->connections as $i => $cn)
        if($this->connections[$i] == $conn)
          unset($this->connections[$i]);

    return $this->connections;
  }

  public function close(){

  }

  public function send($conn, $message){
      $bufferSize = 4096;
  		$opcode = 1;
      // echo 'tosend : `'.$message.'`'.PHP_EOL;
  		if (is_object($message)) {
  			$message = (string)$message;
  		}

      if( $this->dispachEvent('MessageSending', $conn, $message)->isPropagationStopped() )
        return false;


      $socket = null;

      foreach ($this->sockets as $sc)
        if($conn->isThatSocket($sc)){
          $socket = $sc;
          continue;
        }

      if( is_null($socket) )
        return false;

      $messageLength  = strlen($message);
  		$frameCount     = ceil($messageLength / $bufferSize);

  		if ($frameCount == 0)
        $frameCount   = 1;

  		$maxFrame               = $frameCount - 1;
  		$lastFrameBufferLength  = ($messageLength % $bufferSize) != 0 ? ($messageLength % $bufferSize) : ($messageLength != 0 ? $bufferSize : 0);

  		for ($i=0; $i<$frameCount; $i++) {
  			$fin     = $i != $maxFrame ? 0 : 128;
  			$opcode  = $i != 0 ? 0 : $opcode;

  			$bufferLength = $i != $maxFrame ? $bufferSize : $lastFrameBufferLength;

  			if ($bufferLength <= 125) {
  				$payloadLength                = $bufferLength;
  				$payloadLengthExtended        = '';
  				$payloadLengthExtendedLength  = 0;
  			}
  			elseif ($bufferLength <= 65535) {
  				$payloadLength                = 126;
  				$payloadLengthExtended        = pack('n', $bufferLength);
  				$payloadLengthExtendedLength  = 2;
  			}
  			else {
  				$payloadLength = 127;
  				$payloadLengthExtended        = pack('xxxxN', $bufferLength); // pack 32 bit int, should really be 64 bit int
  				$payloadLengthExtendedLength  = 8;
  			}

  			$buffer = pack('n', (($fin | $opcode) << 8) | $payloadLength) . $payloadLengthExtended . substr($message, $i*$bufferSize, $bufferLength);

  			$left = 2 + $payloadLengthExtendedLength + $bufferLength;
  			do {
  				$sent = @socket_send($socket, $buffer, $left, 0);
  				if ($sent === false) return false;

  				$left -= $sent;
  				if ($sent > 0) $buffer = substr($buffer, $sent);
  			}
  			while ($left > 0);
  		}
      // echo 'message - sended'.PHP_EOL;
      $this->dispachEvent('MessageSended', $conn, $message);

  		return true;
  }

  private function error($message, $is_exception = false){

		$lastErrorCode    = socket_last_error($this->socket);
		$lastErrorMessage = socket_strerror($lastErrorCode);
    $message         .= '`'.$lastErrorMessage.'['.$lastErrorCode.']`';

    if( $is_exception ){
      throw new Exception($message);
    }else{
      trigger_error($message, E_USER_WARNING);
    }

  }

  private function dispachEvent($type = 'none', $connection, $message = null){
    $event = new WebSocketEvent($type, $connection, $message, $this);
    foreach ($this->listenners as $listenner)
      if( !$event->isPropagationStopped() ){
        app()->call([$listenner, 'on'.$type], ['event' => $event]);
      }

    return $event;
  }

  private function getConnection($socket, $trigger_events){
    if( $trigger_events ){
      foreach ($this->connections as $connection)
        if( $connection->isThatSocket($socket) ){
          return $connection;
        }
    }else{
      foreach ($this->inlineConnections as $connection)
      if( $connection->isThatSocket($socket) ){
        return $connection;
      }
    }

    return null;
  }

  private function newConnection($socket, $trigger_events){
    $connection = new WebSocketConnection($socket, $this);
    if( $trigger_events ){
        $this->sockets[]      = $socket;
        $this->connections[]  = $connection;
    }else{
      $this->inlineSockets[]      = $socket;
      $this->inlineConnections[]  = $connection;
    }
  }

  private function acceptConnection($connection, $buffer, $trigger_events){
    if( $trigger_events ){
      if( !$this->dispachEvent('Connecting', $connection)->isPropagationStopped() ){
        $connection->accept($buffer);
        $this->dispachEvent('Connected', $connection);
      }else{
        $connection->disconnect();
        $this->removeConnection($connection);
      }
    }else{
      $connection->accept($buffer);
    }
  }

  private function newMessage($connection, $message, $trigger_events){
    if( $trigger_events ){
      $this->dispachEvent('MessageReceived', $connection, $message);
    }else {
      $this->push($message);
    }
  }

  private function lostConnection($socket, $trigger_events){
    $connection = $this->getConnection($socket, $trigger_events);
    if( $trigger_events ){
      $this->dispachEvent('Disconnected', $connection);

      foreach ($this->sockets as $i => $sc)
        if($this->sockets[$i] == $socket)
          unset($this->sockets[$i]);

      foreach ($this->connections as $i => $cn)
        if($this->connections[$i] == $connection)
          unset($this->connections[$i]);
    }else{
      foreach ($this->inlineSockets as $i => $sc)
      if($this->inlineSockets[$i] == $socket)
      unset($this->inlineSockets[$i]);

      foreach ($this->inlineConnections as $i => $cn)
      if($this->inlineConnections[$i] == $connection)
      unset($this->inlineConnections[$i]);
    }
  }

  private function hybi10Encode($payload, $type = 'text', $masked = true)
	{
		$frameHead = array();
		$frame = '';
		$payloadLength = strlen($payload);
		switch ($type)
		{
			case 'text' : $frameHead[0] = 129; break;
			case 'close' : $frameHead[0] = 136; break;
			case 'ping' : $frameHead[0] = 137; break;
			case 'pong' : $frameHead[0] = 138; break;
		}
		if ($payloadLength>65535){
			$payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
			$frameHead[1] = ($masked===true) ? 255 : 127;
			for ($i = 0; $i<8; $i++)
				$frameHead[$i + 2] = bindec($payloadLengthBin[$i]);
			if ($frameHead[2]>127){
				$this->close(1004);
				return false;
			}
		}elseif ($payloadLength>125){
			$payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
			$frameHead[1] = ($masked===true) ? 254 : 126;
			$frameHead[2] = bindec($payloadLengthBin[0]);
			$frameHead[3] = bindec($payloadLengthBin[1]);
		}
		else
			$frameHead[1] = ($masked===true) ? $payloadLength + 128 : $payloadLength;
		foreach (array_keys($frameHead) as $i)
			$frameHead[$i] = chr($frameHead[$i]);
		if ($masked===true){
			$mask = array();
			for ($i = 0; $i<4; $i++)
				$mask[$i] = chr(rand(0, 255));
			$frameHead = array_merge($frameHead, $mask);
		}
		$frame = implode('', $frameHead);
		for ($i = 0; $i<$payloadLength; $i++)
			$frame .= ($masked===true) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
		return $frame;
	}

}
