<?php
namespace Arsenii\WebSockets\Connection;

/**
 *
 */

use Arsenii\WebSockets\Lib\ConnectionInterface;
use Arsenii\WebSockets\Lib\ServerInterface;

final class WebSocketConnection implements ConnectionInterface
{
  const STATE_CONNECTING  = 1;
  const STATE_OPEN        = 2;
  const STATE_CLOSED      = 3;

  private $socket;
  private $server;
  public $id;
  public $state = 1;

  function __construct($socket = null, ServerInterface $server = null){

      $this->socket = $socket;
      $this->server = $server;

      $this->id     = uniqid();

  }

  public function accept($buffer = null){
    if ($this->state != self::STATE_CONNECTING) {
			throw new Exception('Unable to perform handshake, client is not in connecting state');
		}

    $headers    = array();

		foreach (explode("\r\n", $buffer) as $line) {
			if (strpos($line, ': ') !== false) {
				list($key, $value) = explode(': ', $line);
				$headers[trim($key)] = trim($value);
			}
		}

		$key        = isset($headers['Sec-WebSocket-Key']) ? $headers['Sec-WebSocket-Key'] : '';
		$hash       = base64_encode(sha1($key.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

		$headers = array(
			'HTTP/1.1 101 Switching Protocols',
			'Upgrade: websocket',
			'Connection: Upgrade',
			'Sec-WebSocket-Accept: '.$hash
		);

		$headers    = implode("\r\n", $headers)."\r\n\r\n";
		$left       = strlen($headers);

		do {
			$sent     = @socket_send($this->socket, $headers, $left, 0);

			if ($sent === false) {
				$error  = $this->server->error('Sending handshake failed:');
			}

			$left    -= $sent;

			if ($sent > 0) {
				$headers = substr($headers, $sent);
			}
		}
		while ($left > 0);

		$this->state = self::STATE_OPEN;
  }

  public function isThatSocket($socket = null){
    return $socket === $this->socket;
  }

  public function send($message = null){
    if ($this->state == self::STATE_CLOSED) {
			throw new Exception('Unable to send message, connection has been closed');
		}

		$this->server->send($this, $message);
  }

  public function assign($attribute = null, $value = null){

  }

  public function is($attribute = null, $value = null){

  }

  public function disconnect(){

  }
}
