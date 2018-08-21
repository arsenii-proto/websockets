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
    const TIMEOUT = 0; // 100000000

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

        // Create context from options argument
        $context    = self::context($context_options);

        Log::info('Stream Open ['. $address .']', Log::LEVEL_DEBUG);

        // Open stream with passed address and context
        $stream     = stream_socket_server( $address, $errno, $errmsg, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context );
        
        // If stream aren't make exception
        if(! $stream ) {
            
            throw new Exception( $errmsg );
        }
        
        // Put stream crypto enabled by passed argument enable_crypto
        stream_socket_enable_crypto( $stream, $enable_crypto );

        if ( function_exists( 'socket_import_stream' ) ) {

            // Import stream for set options 
            $socket = socket_import_stream($stream);            
            @socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
            @socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);
        }

        // Disable Stream Blocking 
        stream_set_blocking($stream, 0);

        // Generate uniqid for Stream
        while(
                ( $struid = uniqid( count( self::$sockets ) + 1, !0 ) ) != null
            &&  ( isset( self::$sockets[ $struid ] ) )
        );        
        
        // Assing Stream to sockets array by uniqid key 
        self::$sockets[ $struid ] = $stream;

        return $struid;
    }

    /**
     * Make stream context.
     *
     * @param  array|null  $options
     * @return void
     */
    protected static function context( $context_options = [] ){

        // If wasn't specified backlog option in options put it by default
        if(! isset( $context_options['socket']['backlog'] ) )
            $context_options['socket']['backlog'] = self::DEFAULT_BACKLOG;

        // return created stream context
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

        // Target argument can't by missed and send as second argument listener
        if( 
                is_object( $target )
            &&  $target instanceof Closure 
            &&  is_null( $callback )
                
        ){
            // Take callback from target argument and put target as null
            $callback   = $target;
            $target     = null;
        }

        // Check available of event and callback presence
        if(
                (! in_array( $event_type, self::$acceptedEvents ) )
            ||  is_null( $callback )
        )
            return false;

        $evuid = null;

        // Generate uniqid for event
        while(
                ( $evuid = uniqid( count( self::$events ) + 1, !0 ) ) != null
            &&  ( isset( self::$events[ $evuid ] ) )
        );

        // Put event in static events array by uniqid key
        self::$events[ $evuid ] = (object) [

            'callback'  => $callback,
            'target'    => $target,
            'type'      => $event_type,

        ];

        return $evuid;
        
    }

    /**
     * Unset registered callback on event by target.
     *
     * @param  string   $event
     * @param  string   $target
     * @return void
     */
    public static function off( string $type = 'read', string $target = '' ){

        // Check event type and target presence
        if( 
                empty( $type ) 
            ||  empty( $target ) 
        )
            return false;

        // Search events with that type and for this target
        foreach ( self::$events as $evuid => $event ) {
            
            if( $event->target == $target && $event->type == $type )
                unset( self::$events[ $evuid ] ); // Remove Event from static event array
        }

        return true;
        
    }

    /**
     * Unset registered callback on event by target.
     *
     * @param  string   $target
     * @return void
     */
    public static function offEvent( string $target = '' ){

        // Check target argument presence
        if( 
                empty( $target )
            ||  (! isset( self::$events[ $target ] ) )
        )
            return false;

        unset( self::$events[ $target ] ); // Remove event from static events array by uniqid key

        return true;
        
    }

    /**
     * Unset registered callbacks on events for socket.
     *
     * @param  string   $target
     * @return void
     */
    public static function offSocket( string $target = '' ){
        
        // Check target argument presence
        if( 
                empty( $target )
            ||  (! isset( self::$sockets[ $target ] ) )
        )
            return false;
        
        // Search events for this target
        foreach ( self::$events as $evuid => $event ) {
            
            if( $event->target == $target )
                unset( self::$events[ $evuid ] ); // Remove event from static events array
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
        
        // If socket are empty dispatch non target listeners
        if( is_null( $socket ) ){

            // Search events with type passed in argument type
            foreach ( self::$events as $evuid => $event )
                    if(
                            $event->type == $type
                        &&  $event->callback instanceof Closure
                    ){

                        $callback = $event->callback; // Call Closure from object aren't possible
                        $callback(); // Make call
                    }
                        
            return true;
        }

        $target     = null;

        // Search socket uniqid from static sockets array by socket
        foreach ( self::$sockets as $socuid => $tsocket) 
            if( $socket == $tsocket ){

                $target = $socuid;
            }
        
        if(! $target )
            return null;

        // Search events with type and for target passed in arguments
        foreach ( self::$events as $event ){
            if(
                    $event->type == $type
                &&  $event->target == $target
                &&  $event->callback instanceof Closure
            ){

                $callback = $event->callback; // Call Closure from object aren't possible
                $callback( $target, $data ); // Make call
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

            Log::info('Loop Start Iteration', Log::LEVEL_DEBUG);
            
            $read   = self::$sockets; // Add all sockets for loop
            $write  = [];
            $except = [];
            
            // Waiting read/write/signal/timeout events.
            $statuses = @stream_select($read, $write, $except, 0, self::TIMEOUT);
            
            // Dispatch event `tick` without socket 
            self::dispatch('tick');

            // Dispatch event `tickonce` without socket 
            self::dispatch('tickonce');
            
            // Check changes in statuses 
            if (! $statuses )
            continue;
            
            if ( $read )
                foreach ( $read as $socket ){ // Dispatch Read events for Read sockets 
                    Log::info('Loop Read', Log::LEVEL_DEBUG);
                    self::dispatch('read', $socket);
                }

            if ($write) 
                foreach ( $write as $socket ){// Dispatch Write events for Write sockets 
                    Log::info('Loop Write', Log::LEVEL_DEBUG);
                    self::dispatch('write', $socket);
                }

            if($except) 
                foreach ( $except as $socket ){// Dispatch Except events for Except sockets 
                    Log::info('Loop Except', Log::LEVEL_DEBUG);
                    self::dispatch('except', $socket);
                }
            
            Log::info('Loop end Iteration', Log::LEVEL_DEBUG);
        }
    }

    /**
     * Accept new Scoket.
     *  
     * @param  string $target
     * @return void
     */
    public static function accept( $target ){

        // Check if socket target are presence in static sockets array
        if(! isset( self::$sockets[ $target ] ) )
            return false;

        $struid     = null;
        $socket     = self::$sockets[ $target ]; // Take socket from static sockets array
        $new_socket = @stream_socket_accept($socket, 0); // Accept socket

        // If accepted socket aren't exit
        if(! $new_socket ) 
            return false;

        // Generate uniqid for Socket
        while(
                ( $struid = uniqid( count( self::$sockets ) + 1, !0 ) ) != null
            &&  ( isset( self::$sockets[ $struid ] ) )
        );        
        
        Log::comment('Accept new Socket ['. $struid .']', Log::LEVEL_DEBUG);
        
        // Assing Socket to sockets array by uniqid key 
        self::$sockets[ $struid ] = $new_socket;

        return $struid;
        
    }

    /**
     * Get Scoket Address.
     *  
     * @param  string $target
     * @return void
     */
    public static function getAddress( $target ){

        // Check if socket target are presence in static sockets array
        if(! isset( self::$sockets[ $target ] ) )
            return false;

        $socket     = self::$sockets[ $target ]; // Take socket from static sockets array

        return @stream_socket_get_name( $socket, !0 );

    }

    /**
     * Get Scoket Meta data.
     *  
     * @param  string $target
     * @return void
     */
    public static function getMeta( $target ){
        
        // Check if socket target are presence in static sockets array
        if(! isset( self::$sockets[ $target ] ) )
            return false;

        $socket     = self::$sockets[ $target ];// Take socket from static sockets array

        return @stream_get_meta_data( $socket );

    }

    /**
     * Get Scokets Last Error.
     *  
     * @param  string|null $target
     * @return void
     */
    public static function lastError( $target = null ){

        // Check if target argument was passed
        if(! is_null( $target ) ){
            
            // Check if socket target are presence in static sockets array
            if(! isset( self::$sockets[ $target ] ) )
                return false;
    
            $socket     = self::$sockets[ $target ];// Take socket from static sockets array
    
            return @socket_last_error( $socket); // Return Socket Last Error
        }

        return @socket_last_error(); // Return Stream Last Error

    }

    /**
     * Get Scokets Last Error Message.
     *  
     * @param  string|null $target
     * @return void
     */
    public static function lastErrorMessage( $target = null ){
        
        return @socket_strerror( self::lastError( $target ) ); // Return error message by code

    }

    /**
     * Set Scoket Blocking on/off.
     *  
     * @param  string   $target
     * @param  bool     $on
     * @return void
     */
    public static function blocking( $target, bool $on = false ){
        
        // Check if socket target are presence in static sockets array
        if(! isset( self::$sockets[ $target ] ) )
            return false;

        $socket     = self::$sockets[ $target ];// Take socket from static sockets array

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
        
        // Check if socket target are presence in static sockets array
        if(! isset( self::$sockets[ $target ] ) )
            return false;

        $socket     = self::$sockets[ $target ];// Take socket from static sockets array

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
        
        // Check if socket target are presence in static sockets array
        if(! isset( self::$sockets[ $target ] ) )
            return false;

        $socket     = self::$sockets[ $target ];// Take socket from static sockets array

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
        
        // Check if socket target are presence in static sockets array

        // Log::comment("FREAD $target - $size", Log::LEVEL_MASTER);
        
        if(! isset( self::$sockets[ $target ] ) )
            return false;

        if(! $size > 0 )
            return false;

        $socket     = self::$sockets[ $target ];// Take socket from static sockets array

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
    public static function FREAD( string $target, int $size = 65535, bool $bin2hex = false ){
        
        // Check if socket target are presence in static sockets array

        // Log::comment("FREAD $target - $size", Log::LEVEL_MASTER);

        if(! isset( self::$sockets[ $target ] ) )
            return false;

        if(! $size > 0 )
            return false;

        $socket     = self::$sockets[ $target ];// Take socket from static sockets array
        
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
        
        // Check if socket target are presence in static sockets array
        if(! isset( self::$sockets[ $target ] ) )
            return false;

        if(! $size > 0 )
            return false;

        $socket     = self::$sockets[ $target ];// Take socket from static sockets array

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
        
        // Check if socket target are presence in static sockets array
        if(! isset( self::$sockets[ $target ] ) )
            return false;

        $socket     = self::$sockets[ $target ];// Take socket from static sockets array

        return @fclose( $socket );
        
    }

    /**
     * is Scoket FEOF.
     *  
     * @param  string   $target
     * @return bool
     */
    public static function FEOF( $target ){
        
        // Check if socket target are presence in static sockets array
        if(! isset( self::$sockets[ $target ] ) )
            return false;

        $socket     = self::$sockets[ $target ];// Take socket from static sockets array

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

        // Log::comment("FREAD $target - $size", Log::LEVEL_MASTER);
        
        // Check if socket target are presence in static sockets array
        if(! isset( self::$sockets[ $target ] ) )
            return false;

        if(! $size > 0 )
            return false;

        $socket     = self::$sockets[ $target ];// Take socket from static sockets array
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
        
        // Check if socket target are presence in static sockets array
        if(! isset( self::$sockets[ $target ] ) )
            return false;

        $socket     = self::$sockets[ $target ];// Take socket from static sockets array

        return !( !is_resource( $socket ) || feof( $socket ) );
    }

}