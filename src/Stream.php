<?php

namespace Arsenii\WebSockets;

use Arsenii\WebSockets\Log;
use Closure;

class Stream
{
    /**
     * Default backlog. Backlog is the maximum length of the queue of pending connections.
     *
     * @var int
     */
    const DEFAULT_BACKLOG = 102400;

    /**
     * Default select timeout.
     *
     * @var int
     */
    const TIMEOUT = 1000000;

    /**
     * Array with sockets saved by unique id generated with their stacked order.
     *
     * @var array
     */
    protected static $sockets = [];

    /**
     * Array with stream/socket events saved by unique id generated with their stacked order.
     *
     * @var array
     */
    protected static $events = [];

    /**
     * Array with stream/socket accepted events.
     *
     * @var array
     */
    protected static $acceptedEvents = [
        'read',
        'write',
        'except',
        'tick',
        'tickonce',
    ];
    
    /**
     * Open new WebSockets stream.
     *
     * @param  string   $address
     * @param  array    $context_options
     * @param  bool     $enable_crypto
     * @return string
     */ 
    public static function open( string $address = '0.0.0.0', $context_options = [], bool $enable_crypto = false ){

        $errno      = null;
        $errmsg     = null;
        $socket     = null;
        $struid     = null;
        $context    = static::context($context_options);
        Log::info('open stream server');
        $stream     = stream_socket_server( $address, $errno, $errmsg, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context );
        
        if(! $stream ) {
            
            throw new Exception( $errmsg );
        }
        
        Log::info('stream Crypto server');
        stream_socket_enable_crypto( $stream, $enable_crypto );

        if ( function_exists( 'socket_import_stream' ) ) {

            $socket = socket_import_stream($stream);
            @socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
            @socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);
        }

        Log::info('stream Blocking server');
        stream_set_blocking($stream, 0);

        Log::info('start stream assign uid server');
        while(
                ( $struid = uniqid( count( static::$sockets ) + 1, !0 ) ) != null
            &&  ( isset( static::$sockets[ $struid ] ) )
        );        
        Log::info('stream assign uid server');
        static::$sockets[ $struid ] = $stream;

        return $struid;
    }

    /**
     * Make stream context.
     *
     * @param  array|null  $options
     * @return void
     */
    protected static function context( $context_options = [] ){

        if(! isset( $context_options['socket']['backlog'] ) )
            $context_options['socket']['backlog'] = static::DEFAULT_BACKLOG;

        return stream_context_create($context_options);

    }

    /**
     * Register callback on event.
     *
     * @param  string           $event_type
     * @param  string|Closure   $target
     * @param  Closure          $callback
     * 
     * @return void
     */
    public static function on( string $event_type = 'read', $target = '', $callback = null ){

        if( 
                is_object( $target )
            &&  $target instanceof Closure 
            &&  is_null( $callback )
                
        ){
            $callback   = $target;
            $target     = null;
        }

        if(
                (! in_array( $event_type, static::$acceptedEvents ) )
            ||  is_null( $callback )
        )
            return false;

        $evuid = null;

        while(
                ( $evuid = uniqid( count( static::$events ) + 1, !0 ) ) != null
            &&  ( isset( static::$events[ $evuid ] ) )
        );

        static::$events[ $evuid ] = (object) [

            'callback'  => $callback,
            'target'    => $target,
            'type'      => $event_type,

        ];

        return $evuid;
        
    }

    /**
     * Unset registered callback on event by target.
     *
     * @param  string   $target
     * @return void
     */
    public static function off( string $target = '' ){

        if( 
                empty( $target )
            &&  (! isset( static::$events[ $target ] ) )
        )
            return false;

        unset( static::$events[ $target ] );

        return true;
        
    }

    /**
     * Unset registered callbacks on events for socket.
     *
     * @param  string   $target
     * @return void
     */
    public static function offSocket( string $target = '' ){

        if( 
                empty( $target )
            &&  (! isset( static::$sockets[ $target ] ) )
        )
            return false;
        
        foreach ( static::$events as $evuid => $event ) {
            
            if( $event->target == $target )
                unset( static::$events[ $evuid ] );
        }

        return true;
        
    }

    /**
     * Trigger registered callbacks on events for socket.
     *
     * @param  string   $type
     * @param  resource $socket
     * @param  string   $data
     * @return void
     */
    protected static function dispatch( string $type = '', $socket = null, $data = null ){
        
        if( is_null( $socket ) ){

            foreach ( static::$events as $evuid => $event )
                    if(
                            $event->type == $type
                        &&  $event->callback instanceof Closure
                    ){

                        $callback = $event->callback;
                        $callback();
                    }
                        
            return true;
        }

        foreach ( static::$sockets as $target => $tsocket){
            if( $socket == $tsocket ){
                foreach ( static::$events as $event ){
                    if(
                            $event->type == $type
                        &&  $event->target == $target
                        &&  $event->callback instanceof Closure
                    ){

                        $callback = $event->callback;
                        $callback( $target, $data );
                    }
                }
            }
        }
        
        return true;
        
    }

    /**
     * Loop Scokets changes.
     *
     * @return void
     */
    public static function loop(){        

        while ( !0 ) {

            Log::info('start while');
            
            $read   = static::$sockets;
            $write  = [];
            $except = [];
            
            // Waiting read/write/signal/timeout events.
            $statuses = @stream_select($read, $write, $except, 0, static::TIMEOUT);
            
            Log::info($statuses);
            
            static::dispatch('tick');
            static::dispatch('tickonce');
            
            if (! $statuses )
            continue;
            
            if ( $read )
                foreach ( $read as $socket ){
                    Log::info('read');
                    static::dispatch('read', $socket);
                }

            if ($write) 
                foreach ( $write as $socket ){
                    Log::info('write');
                    static::dispatch('write', $socket);
                }

            if($except) 
                foreach ( $except as $socket ){
                    Log::info('except');
                    static::dispatch('except', $socket);
                }
            
            Log::info('end while');                    
        }
    }

    /**
     * Accept new Scoket.
     *  
     * @param  string $target
     * @return void
     */
    public static function accept( $target ){

        if(! isset( static::$sockets[ $target ] ) )
            return false;

        $struid     = null;
        $socket     = static::$sockets[ $target ];
        $new_socket = @stream_socket_accept($socket, 0);

        if(! $new_socket )
            return false;

        while(
                ( $struid = uniqid( count( static::$sockets ) + 1, !0 ) ) != null
            &&  ( isset( static::$sockets[ $struid ] ) )
        );        
        Log::info('stream assign uid socket');
        static::$sockets[ $struid ] = $new_socket;

        return $struid;
        
    }

    /**
     * Get Scoket Address.
     *  
     * @param  string $target
     * @return void
     */
    public static function getAddress( $target ){

        if(! isset( static::$sockets[ $target ] ) )
            return false;

        $socket     = static::$sockets[ $target ];

        return @stream_socket_get_name( $socket, !0 );

    }

    /**
     * Get Scoket Meta data.
     *  
     * @param  string $target
     * @return void
     */
    public static function getMeta( $target ){

        if(! isset( static::$sockets[ $target ] ) )
            return false;

        $socket     = static::$sockets[ $target ];

        return @stream_get_meta_data( $socket );

    }

    /**
     * Get Scokets Last Error.
     *  
     * @param  string|null $target
     * @return void
     */
    public static function lastError( $target = null ){

        if(! is_null( $target ) ){

            if(! isset( static::$sockets[ $target ] ) )
                return false;
    
            $socket     = static::$sockets[ $target ];
    
            return @socket_last_error( $socket);
        }

        return @socket_last_error( $socket);

    }

    /**
     * Get Scokets Last Error Message.
     *  
     * @param  string|null $target
     * @return void
     */
    public static function lastErrorMessage( $target = null ){

        if(! is_null( $target ) ){

            if(! isset( static::$sockets[ $target ] ) )
                return false;
    
            $socket     = static::$sockets[ $target ];
            $ercode     = @socket_last_error( $socket);
            return @socket_strerror( $ercode );
        }

        $ercode = @socket_last_error( $socket);
        return @socket_strerror( $ercode );

    }

    /**
     * Set Scoket Blocking on/off.
     *  
     * @param  string   $target
     * @param  bool     $on
     * @return void
     */
    public static function blocking( $target, bool $on = false ){

        if(! isset( static::$sockets[ $target ] ) )
            return false;

        $socket     = static::$sockets[ $target ];

        return @stream_set_blocking( $socket, $on );

    }

    /**
     * Set Scoket Read Buffer size.
     *  
     * @param  string   $target
     * @param  int      $position
     * @return void
     */
    public static function readBufferPosition( $target, int $position = 0 ){

        if(! isset( static::$sockets[ $target ] ) )
            return false;

        $socket     = static::$sockets[ $target ];

        if (function_exists('stream_set_read_buffer')) {
            return @stream_set_read_buffer( $socket, $position );
        }

        return true;

    }

    /**
     * Set Scoket Crypto.
     *  
     * @param  string   $target
     * @param  bool     $on
     * @return void
     */
    public static function crypto( $target, bool $on = false ){

        if(! isset( static::$sockets[ $target ] ) )
            return false;

        $socket     = static::$sockets[ $target ];

        return @stream_socket_enable_crypto(
            $socket,
            $on,
            STREAM_CRYPTO_METHOD_SSLv2_SERVER | STREAM_CRYPTO_METHOD_SSLv23_SERVER
        );
    }

    /**
     * Get Scoket Buffer.
     *  
     * @param  string   $target
     * @param  int      $size
     * @return void
     */
    public static function buffer( $target, int $size = 65535 ){

        if(! isset( static::$sockets[ $target ] ) )
            return false;

        if(! $size > 0 )
            return false;

        $socket     = static::$sockets[ $target ];

        return @fread( $socket, $size );

    }

    /**
     * is Scoket FEOF.
     *  
     * @param  string   $target
     * @param  int      $size
     * @param  bool     $bin2hex
     * @return string
     */
    public static function FREAD( string $target, int $size = 0, bool $bin2hex = false ){

        if(! isset( static::$sockets[ $target ] ) )
            return false;

        if(! $size > 0 )
            return false;

        $socket     = static::$sockets[ $target ];
        
        if( $bin2hex )
            return bin2hex( fread( $socket, $size ) );

        return fread( $socket, $size );
    }

    /**
     * is Scoket FEOF.
     *  
     * @param  string   $target
     * @param  string   $data
     * @param  int      $size
     * @return int
     */
    public static function FWRITE( string $target, string $data, int $size = 0 ){

        if(! isset( static::$sockets[ $target ] ) )
            return false;

        if(! $size > 0 )
            return false;

        $socket     = static::$sockets[ $target ];

        return @fwrite( $socket, $data, $size );
        
    }

    /**
     * is Scoket FEOF.
     *  
     * @param  string   $target
     * @param  string   $data
     * @param  int      $size
     * @return int
     */
    public static function FCLOSE( string $target ){

        if(! isset( static::$sockets[ $target ] ) )
            return false;

        $socket     = static::$sockets[ $target ];

        return @fclose( $socket );
        
    }

    /**
     * is Scoket FEOF.
     *  
     * @param  string   $target
     * @return bool
     */
    public static function FEOF( $target ){

        if(! isset( static::$sockets[ $target ] ) )
            return false;

        $socket     = static::$sockets[ $target ];

        return feof( $socket );
    }

    /**
     * Check Scoket FEOF.
     *  
     * @param  string   $target
     * @param  int      $size
     * @return bool
     */
    public static function checkFEOF( $target, int $size = 65535 ){

        if(! isset( static::$sockets[ $target ] ) )
            return false;

        if(! $size > 0 )
            return false;

        $socket     = static::$sockets[ $target ];
        $buffer     = @fread( $socket, $size );

        return feof( $socket ) || !is_resource( $socket ) || $buffer === false;
    }

    /**
     * Check Scoket Alive.
     *  
     * @param  string   $target
     * @return bool
     */
    public static function alive( $target ){

        if(! isset( static::$sockets[ $target ] ) )
            return false;

        $socket     = static::$sockets[ $target ];

        return !( !is_resource( $socket ) || feof( $socket ) );
    }

}