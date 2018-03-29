<?php
namespace Arsenii\WebSockets\Lib;
/**
 *
 */
use Arsenii\WebSockets\Lib\EventInterface;

interface ListenerInterface
{

  public function onConnecting(EventInterface $event);
  public function onConnected(EventInterface $event);

  public function onMessageReceived(EventInterface $event);

  public function onMessageSending(EventInterface $event);
  public function onMessageSended(EventInterface $event);

  public function onDisconnecting(EventInterface $event);
  public function onDisconnected(EventInterface $event);

}
