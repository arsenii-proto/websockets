<?php
namespace Arsenii\WebSockets\Lib;

/**
 *
 */

use Arsenii\WebSockets\Lib\ConnectionInterface;
use Arsenii\WebSockets\Lib\ListenerInterface;

interface ServerInterface
{

  public function run();
  public function push($message = null, ConnectionInterface $conn = null);
  public function addListener(ListenerInterface $listener = null);
  public function removeListener(ListenerInterface $listener = null);
  public function getConnections();
  public function removeConnection(ConnectionInterface $conn = null);
  public function close();

}
