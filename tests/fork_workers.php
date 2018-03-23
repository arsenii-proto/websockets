<?php

if (! function_exists('pcntl_fork')) die('PCNTL functions not available on this PHP installation');
print "Start! [". date('Y-m-d H:i:s.u') ."}]\n\n";

$proc = 'master';

for ($x = 1; $x < 5; $x++) {
    $pid = pcntl_fork(); //создаём форк

   if ($pid == -1) {
        die("error: pcntl_fork");
    } elseif ($pid) { //родительский процесс

    } else { //дочерний процесс
        $proc = 'child - '.$x;
        break; //выходим из цикла, чтобы дочерние процессы создавались только из родителя
    }
}

$sec = rand( 1, 10 );

sleep( $sec );

print "Done! ". $proc .' -> ('. $sec .') '. date('Y-m-d H:i:s.u') ." :^)\n\n";
?>