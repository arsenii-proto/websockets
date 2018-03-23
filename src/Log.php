<?php

namespace Arsenii\WebSockets;

use Closure;
use \Carbon\Carbon;

use \Symfony\Component\Console\Output\ConsoleOutput;

class Log
{   

    /**
     * Log Level Debug.
     *
     * @var int
     */
    const LEVEL_DEBUG = 1;

    /**
     * Log Level Dev.
     *
     * @var int
     */
    const LEVEL_DEV = 2;

    /**
     * Log Level Master.
     *
     * @var int
     */
    const LEVEL_MASTER = 3;

    /**
     * Current Log Level.
     *
     * @var int
     */
    const CURRENT_LEVEL = 3;

    /**
     * output
     *
     * @var int
     */
    protected static $output = null;

    /**
     * log to console.
     *
     * @param  string   $type
     * @param  string   $out
     * @param  int      $level
     * @return void
     */    
    public static function log( string $type = 'info', string $out = 'log message', int $level = 3 ){

        if(! static::$output )
            static::$output = new ConsoleOutput();

        if( $level == static::CURRENT_LEVEL ){

            static::$output->write('<fg=green;options=bold,underscore>['. Carbon::now()->format("m-d-Y H:i:s.u") .']  </>');

            switch( $type ){

                case 'info': 
                static::$output->writeln('<info>'. $out .'</info>');
                break;

                case 'comment': 
                static::$output->writeln('<comment>'. $out .'</comment>');
                break;

                case 'question': 
                static::$output->writeln('<question>'. $out .'</question>');
                break;

                case 'error': 
                static::$output->writeln('<error>'. $out .'</error>');
                break;

                default: 
                static::$output->writeln($out);
                break;
            }

        }        

    }

    /**
     * log to console with type `info`.
     *
     * @param  string   $out
     * @param  int      $level
     * @return void
     */  
    public static function info( string $out = 'log message', int $level = 3 ){
        
        static::log('info', $out, $level);
    }

    /**
     * log to console with type `comment`.
     *
     * @param  string   $out
     * @param  int      $level
     * @return void
     */  
    public static function comment( string $out = 'log message', int $level = 3 ){
        
        static::log('comment', $out, $level);
    }

    /**
     * log to console with type `question`.
     *
     * @param  string   $out
     * @param  int      $level
     * @return void
     */  
    public static function question( string $out = 'log message', int $level = 3 ){
        
        static::log('question', $out, $level);
    }

    /**
     * log to console with type `error`.
     *
     * @param  string   $out
     * @param  int      $level
     * @return void
     */  
    public static function error( string $out = 'log message', int $level = 3 ){
        
        static::log('error', $out, $level);
    }

    /**
     * log to console with type `raw`.
     *
     * @param  string   $out
     * @param  int      $level
     * @return void
     */  
    public static function raw( string $out = 'log message', int $level = 3 ){
        
        static::log('raw', $out, $level);
    }
    
}