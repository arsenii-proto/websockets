<?php
namespace Arsenii\WebSockets\Lib;

/**
 *
 */

interface ClientInterface
{

  public function onConnect();
  public function onMessage($message = null);
  public function send();
  public function onDisconnect();
  public function close();

}
