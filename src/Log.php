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
    public static function log( string $type = 'info', string $out = 'log message', int $level = self::LEVEL_MASTER ){

        if(! self::$output )
            self::$output = new ConsoleOutput();

        if( $level >= self::CURRENT_LEVEL ){
            
            if( self::CURRENT_LEVEL == self::LEVEL_DEBUG ){

                self::$output->write('<fg=green;options=bold,underscore>['. Carbon::now()->format("m-d-Y H:i:s.u") .']  </>');
            }

            switch( $type ){

                case 'info': 
                self::$output->writeln('<info>'. $out .'</info>');
                break;

                case 'comment': 
                self::$output->writeln('<comment>'. $out .'</comment>');
                break;

                case 'question': 
                self::$output->writeln('<question>'. $out .'</question>');
                break;

                case 'error': 
                self::$output->writeln('<error>'. $out .'</error>');
                break;

                default: 
                self::$output->writeln($out);
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
    public static function info( string $out = 'log message', int $level = self::LEVEL_MASTER ){
        
        self::log('info', $out, $level);
    }

    /**
     * log to console with type `comment`.
     *
     * @param  string   $out
     * @param  int      $level
     * @return void
     */  
    public static function comment( string $out = 'log message', int $level = self::LEVEL_MASTER ){
        
        self::log('comment', $out, $level);
    }

    /**
     * log to console with type `question`.
     *
     * @param  string   $out
     * @param  int      $level
     * @return void
     */  
    public static function question( string $out = 'log message', int $level = self::LEVEL_MASTER ){
        
        self::log('question', $out, $level);
    }

    /**
     * log to console with type `error`.
     *
     * @param  string   $out
     * @param  int      $level
     * @return void
     */  
    public static function error( string $out = 'log message', int $level = self::LEVEL_MASTER ){
        
        self::log('error', $out, $level);
    }

    /**
     * log to console with type `raw`.
     *
     * @param  string   $out
     * @param  int      $level
     * @return void
     */  
    public static function raw( string $out = 'log message', int $level = self::LEVEL_MASTER ){
        
        self::log('raw', $out, $level);
    }
    
}