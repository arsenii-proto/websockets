<?php
namespace Arsenii\WebSockets\Lib;
/**
 *
 */
interface ConnectionInterface
{

  public function accept($buffer = null);
  public function isThatSocket($socket = null);
  public function send($message = null);
  public function assign($attribute = null, $value = null);
  public function is($attribute = null, $value = null);
  public function disconnect();

}
