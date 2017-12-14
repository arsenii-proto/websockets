<?php
namespace Arsenii\WebSockets\Lib;
/**
 *
 */
interface EventInterface
{

  public function get($flow = null, $default = null);
  public function has($flow = null);
  public function getType();
  public function stopPropagation();
  public function isPropagationStopped();
  public function match($pattern = null);
  public function send($message = null);
  public function invoke($path = null);

}
