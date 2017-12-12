<?php
namespace Arsenii\WebSockets\Lib;

/**
 *
 */

use Arsenii\WebSockets\Lib\ConnectionInterface;
use Arsenii\WebSockets\Lib\ListennerInterface;

interface ServerInterface
{

  public function run();
  public function push($message = null, ConnectionInterface $conn = null);
  public function addListenner(ListennerInterface $listenner = null);
  public function removeListenner(ListennerInterface $listenner = null);
  public function getConnections();
  public function removeConnection(ConnectionInterface $conn = null);
  public function close();

}
