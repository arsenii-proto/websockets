<?php

namespace Arsenii\WebSockets;

use Arsenii\WebSockets\Facades\Emitter;
use Arsenii\WebSockets\Server;
use Arsenii\WebSockets\Log;

use \ReflectionObject;
use \ReflectionProperty;

class Connection
{
    /**
     * Read buffer size.
     *
     * @var int
     */
    const READ_BUFFER_SIZE = 65535;

    /**
     * Read buffer size.
     *
     * @var int
     */
    const MAX_SEND_BUFFER_SIZE = 1048576;

    /**
     * Max package size.
     *
     * @var int
     */
    const MAX_PACKAGE_SIZE = 10485760;

    /**
     * Status initial.
     *
     * @var int
     */
    const STATUS_INITIAL = 0;

    /**
     * Status connecting.
     *
     * @var int
     */
    const STATUS_CONNECTING = 1;

    /**
     * Status HandShake established.
     *
     * @var int
     */
    const STATUS_HANDSHAKE_ESTABLISHED = 2;

    /**
     * Status connection established.
     *
     * @var int
     */
    const STATUS_ESTABLISHED = 3;

    /**
     * Status closing.
     *
     * @var int
     */
    const STATUS_CLOSING = 4;

    /**
     * Status closed.
     *
     * @var int
     */
    const STATUS_CLOSED = 8;
    
    /**
     * Target
     *
     * @var string
     */
    protected $target = null;
    
    /**
     * Assigned
     *
     * @var string
     */
    protected $Assigned = [];
    
    /**
     * Server
     *
     * @var string
     */
    protected $server = null;
    
    /**
     * Connection id
     *
     * @var string
     */
    protected $id = null;
    
    /**
     * Connection type
     *
     * @var string
     */
    protected $type = null;
    
    /**
     * Remote address.
     *
     * @var string
     */
    protected $address = '';
    
    /**
     * Status
     *
     * @var int
     */
    protected $status = null;
    
    /**
     * Current package Length
     *
     * @var int
     */
    protected $_cpl = 0;
    
    /**
     * Received Buffer
     *
     * @var string
     */
    protected $_buff = '';
    
    /**
     * Received Buffer Length
     *
     * @var int
     */
    protected $_buffl = 0;
    
    /**
     * Buffer to Send
     *
     * @var string
     */
    protected $_sendb = '';
    
    /**
     * Frame data
     *
     * @var array
     */
    protected $frame = [
        'handshake' => '',
        'curlen'    => '',
        'buff'      => '',
        'tmp'       => '',
        'curbuff'   => '',
        'type'      => '',
    ];
    

    /**
     * Create a new WebSockets Connection instance.
     *
     * @param  string   $id
     * @param  string   $target
     * @param  string   $type
     * @param  string   $server
     * @return void
     */
    public function __construct(string $id = null, string $target = null, string $type = null, Server $server = null){
        
        $this->id       = $id;
        $this->target   = $target;
        $this->type     = $type;
        $this->server   = $server;

        $this->status   = self::STATUS_INITIAL;

        $meta = Stream::getMeta( $target ); // Get Scoket Meta data
        
        // If meta don't has timeout expired change connection status to STATUS_CONNECTING
        if(! $meta['timed_out'] ){

            $this->status   = self::STATUS_CONNECTING;
        }else{

            Log::error( Stream::lastErrorMessage( $target ) );
        }

        // Get Socket Ip Adrress and port
        $this->address  = Stream::getAddress( $target );

        // Disable Socket Blocking
        Stream::blocking( $target, !1 );

        // Read 0 Length Buffer ( one more think for checking if socket was accepted )
        Stream::readBufferPosition( $target, 0 );

        Log::info( 'new connection ['. $this->address .']', Log::LEVEL_DEBUG);
        
        // Add read event listener
        Stream::on('read', $target, function(){

            $this->readBuffer();
        });

        if( Emitter::dispatch( 'connecting', $this, '[]' )->isPropagationStopped() ){

            $this->close();

        }
    }

    /**
     * Read socket Buffer.
     *
     * @return void
     */
    protected function readBuffer(){

        // Check if websocket protocol are wss or auto for making `SSL HANDSHAKE`
        if( 
                in_array( $this->type, [ 'wss', 'auto' ] )
            &&  $this->status < self::STATUS_HANDSHAKE_ESTABLISHED
        ){
            
            Log::comment('SSL HANDSHAKE check', Log::LEVEL_DEBUG);
            
            // Enable Crypto on socket
            $result = Stream::crypto( $this->target, !0 );
            
            if( false === $result ) {

                // Check if aren't end of file
                if (! Stream::FEOF( $this->target ) ) {

                    // Read socket headers
                    $buffer = Stream::FREAD( $this->target, self::READ_BUFFER_SIZE );  

                    // if websocket protocol are auto try make not ssl handshake
                    if( 'auto' == $this->type ){
                        
                        // Disable Crypto on socket
                        Stream::crypto( $this->target, !1 );

                        // If Headers have `Sec-WebSocket-Key` key and value perform not ssl Handshake
                        if ( preg_match( "/Sec-WebSocket-Key: *(.*?)\r\n/i", $buffer, $match ) ) {
                            
                            return $this->checkBuffer( $buffer );

                        }

                    }

                    Log::error("SSL Handshake fail.\nBuffer:". $buffer);
                }

                return $this->destroy();

            } elseif( 0 === $result ) {

                return; // There isn't enough data and should try again.
            }
            
            Log::comment("SSL Handshake was successful [". $this->target ."]", Log::LEVEL_DEBUG);
            
            $this->status = self::STATUS_HANDSHAKE_ESTABLISHED;

            // Ckeck if connection have data to send
            $this->checkSend();

            return;
        }
        
        // Read socket headers
        $buffer = Stream::FREAD( $this->target, self::READ_BUFFER_SIZE );

        // Log::comment('On Read ['. $this->target .'] ->'. $buffer, Log::LEVEL_DEBUG);
        
        // Check Received Data
        return $this->checkBuffer( $buffer );
        
    }

    /**
     * Check socket Buffer.
     *
     * @return void
     */
    protected function checkBuffer( $buffer ){

        if( $buffer === '' || $buffer === false ){

            if( Stream::checkFEOF( $this->target, self::READ_BUFFER_SIZE ) ){

                Log::comment('Check Buffer was empty', Log::LEVEL_DEBUG);

                return $this->destroy();
            }
            
        } else {
            
            // Append to connection buffer
            $this->_buff    .= $buffer;
            // Calculate connection buffer length
            $this->_buffl   += strlen( $buffer );
        }


        while ( $this->_buff !== '' ) {
            
            // If current Packet Length
            // Log::comment("cpl -> {$this->cpl}", Log::LEVEL_MASTER);
            if ( $this->_cpl ) {
                
                Log::comment('The current packet length is known.', Log::LEVEL_DEBUG);
                // If CPL are biggest than Buffer Length break while
                if ( $this->_cpl > $this->_buffl ) {
                    
                    Log::comment('Data is not enough for a package', Log::LEVEL_DEBUG);
                    break;
                }

            } else {
                
                // Take current Packet Length from server 
                Log::comment('Get current package length.', Log::LEVEL_DEBUG);
                
                $this->_cpl = $this->server->packlen( $this->id );
                
                // Break while if cpl is 0
                if ( 0 === $this->_cpl ) {
                    
                    Log::comment("The packet length is unknown. {$this->_cpl}", Log::LEVEL_DEBUG);
                    break;

                } elseif (
                        $this->_cpl > 0
                    &&  $this->_cpl <= self::MAX_PACKAGE_SIZE
                ) {
                    
                    // If CPL are biggest than Buffer Length break while
                    Log::comment("223 -> $this->_cpl > ".strlen( $buffer )."\n", Log::LEVEL_DEBUG);
                    if ( $this->_cpl > strlen( $buffer ) ) {

                        Log::comment('Data is not enough for a package.', Log::LEVEL_DEBUG);
                        break;
                    }

                } else {

                    Log::error( 'Error package. package_length = '. var_export($this->_cpl, true) );
                    
                    $this->destroy();
                    return;
                }
            }

            Log::comment('The data is enough for a packet.', Log::LEVEL_DEBUG);
            // IF CPL is egual to Buffer Length
            if ( strlen( $this->_buff ) === $this->_cpl ) {
                
                Log::comment('The current packet length is equal to the length of the buffer', Log::LEVEL_DEBUG);
                $buffreq        = $this->_buff; // Assign connection buffer to tmp variable
                $this->_buff    = ''; // Put connection buffer empty

            } else {

                $buffreq        = substr( $this->_buff, 0, $this->_cpl ); // Assign CPL substing from connection buffer to tmp variable
                $this->_buff    = substr( $this->_buff, $this->_cpl ); // Put connection buffer rest from buffer after current Packet Length
            }
            
            $this->_cpl = 0; // Assign current Packet Length to 0

            $message    = $this->server->decode( $this->id, $buffreq );

            Log::comment('On Message ['. $this->target .']['. $message .']', Log::LEVEL_DEBUG);

            Emitter::dispatch( 'message-received', $this, $message );
        }

    }

    /**
     * Get socket Buffer.
     *
     * @param bool $bin2hex
     * @return string
     */
    public function getBuffer( bool $bin2hex = false ){

        if( $bin2hex )
            return bin2hex( $this->_buff );

        return $this->_buff;
    }

    /**
     * Get frame options.
     *
     * @param string $option
     * @return mixt
     */
    public function getFrame(string $option = null){

        if( isset( $this->frame[ $option ] ) )
            return $this->frame[ $option ];
        
        return null;
    }

    /**
     * Set frame options.
     *
     * @param string    $option
     * @param mixt      $value
     * @return mixt
     */
    public function setFrame(string $option = null, $value = null){

        if( isset( $this->frame[ $option ] ) )
            return ( $this->frame[ $option ] = $value );
        
        return null;
    }

    /**
     * Get socket Buffer Length.
     *
     * @return int
     */
    public function getBufferLen(){

        return strlen( $this->_buff );
    }
    
    /**
     * Get socket Status.
     *
     * @return string
     */
    public function getStatus(){

        return $this->status;
    }

    /**
     * Put data on the send Buffer.
     *
     * @param string $data
     * @return bool
     */
    public function putSend( $data ){

        // IF Connection MAX_SEND_BUFFER_SIZE less than Send Buffer Length with Data Length trigger error
        if ( self::MAX_SEND_BUFFER_SIZE <= strlen( $this->_sendb ) + strlen( $data ) ) {
            
            Log::comment("Error buffer can't be great than MAX_SEND_BUFFER_SIZE");

            return false;
        }

        $this->_sendb .= $data; // Append data to Send Buffer
        
        return true;
    }

    /**
     * Sends data on the connection.
     *
     * @param string $data
     * @param bool   $raw
     * @return void|bool|null
     */
    public function send( $data, $raw = false ){

        // If Connection are in Close or Closing state exit from sending
        if (
                $this->status === self::STATUS_CLOSING
            ||  $this->status === self::STATUS_CLOSED
        ) {

            Log::comment('Send Connection are on close/closing state ['. $this->status .']', Log::LEVEL_DEBUG);
            return false;
        }
        
        // If raw is false data will encoded by server
        if ( false === $raw ) {

            if( in_array( gettype($data), ['object', 'array'] ) ){

                if(
                            gettype($data) == 'object'
                        && method_exists( $data, 'toString') 
                    ){
                    
                    $data = $data->toString();
            
                }else{
            
                    $data = json_encode($data);
            
                }
        
            }
        
            $data = (string) $data;
            
            // receive encoded data from server
            $data   = $this->server->encode( $this->id, $data );

            // If encoded data are empty exit
            if ($data === '') {

                Log::comment('Send Encoded Data are empty ['. $data .']', Log::LEVEL_DEBUG);
                return null;
            }
        }

        // If connection aren't established put data to send buffer
        if ( $this->status < self::STATUS_ESTABLISHED ) {

            Log::comment('Send put data to buffer ['. $data .']', Log::LEVEL_DEBUG);

            $this->putSend( $data );

            return null;
        }

        // If send buffer are empty send directly else put in send buffer
        if ( $this->_sendb === '' ) {

            Log::comment('Send data directly ['. $data .']', Log::LEVEL_DEBUG);
            
            if( Emitter::dispatch(
                    $this->getFrame('handshake') ? 'message-sending' : 'handshake-sending', 
                    $this, 
                    $data )->isPropagationStopped() ){

                return null;

            }else{
                
                $len = Stream::FWRITE( $this->target, $data, 8192 );
                
                Emitter::dispatch( 
                    $this->getFrame('handshake') ? 'message-sending' : 'handshake-sended',  
                    $this, 
                    $data );

                // If length of writed are the same length with data, data was sended
                if ( $len === strlen( $data ) ) {

                    return true;
                }

                // If length of writed aren't the same length with data, push data to send buffer
                if ( $len > 0 ) {

                    $this->putSend( substr($data, $len) );

                } else {

                    // Check if socket are alive
                    if (! Stream::alive( $this->target ) ) {

                        Log::error('Error on Send data, socket are not responding');

                        $this->destroy();

                        return false;
                    }

                    // Socket are alive but data wasn't sended
                    $this->putSend( $data );
                }

                // Add write event listener
                Stream::on( 'write', $this->target, function(){
        
                    $this->baseWrite();
                });

                return null;
            }

        } else {

            // Put data on send buffer
            $this->putSend( $data );
        }
    }

    /**
     * Check if there are data to send.
     *
     * @return void
     */
    public function checkSend(){

        // If send buffer aren't empty add write event listener
        if ( $this->_sendb ) {
            
            Stream::on( 'write', $this->target, function(){
    
                $this->baseWrite();
            });
        }

    }    

    /**
     * Clear Buffer.
     *
     * @param int|null $len
     * @return void
     */
    public function clearBuff(int $len = null){

        // If length wasn't sended, put length of buffer
        if( is_null( $len ) )
            $len = strlen( $this->_buff );

        // clear part of bufer
        $this->_buff = substr( $this->_buff, $len );
    }    

    /**
     * Close connection.
     *
     * @param mixed $data
     * @param bool $raw
     * @return void
     */
    public function close($data = null, $raw = false){

        // If connection has Closed/Closing status exit
        if (
                $this->status === self::STATUS_CLOSING
            ||  $this->status === self::STATUS_CLOSED
        ) {

            return;

        } else {

            // If data aren't null send last data before closing
            if (! is_null( $data ) ) {

                $this->send( $data, $raw );
            }

            $last           = $this->status;
            // Put status Closing
            $this->status   = self::STATUS_CLOSING;
            
            if( Emitter::dispatch( 'disconnecting', $this, '[]' )->isPropagationStopped() ){

                $this->status   = $last;

                return;
            }
        }

        // If send buffer are empty destroy connection
        if ( $this->_sendb === '' ) {

            $this->destroy();
        }
    }

    /**
     * Make HandShake.
     *
     * @param string    $upgrade
     * @param int       $headlen
     * @return void
     */
    public function handshake(string $upgrade, int $headlen){

        // Put status to STATUS_HANDSHAKE_ESTABLISHED
        $this->status = self::STATUS_HANDSHAKE_ESTABLISHED;

        $this->clearBuff( $headlen ); // Clear buffer with headlen length
        
        // Put status to STATUS_HANDSHAKE_ESTABLISHED
        $this->status = self::STATUS_ESTABLISHED;

        Log::comment('Handshake Send Upgrade ['. $upgrade .']', Log::LEVEL_DEBUG);
        
        $this->setFrame('curlen', 0); // Clear Frame Current Buffer length
        $this->setFrame('handshake', true); // Put data on handshake frame
        $this->setFrame('buff', ''); // Clear Frame Buffer
        $this->setFrame('curbuff',''); // Clear Frame Buffer length

        // Send Upgrade
        $this->send( $upgrade, true );         

        // If Frame temporary data aren't empty send temporary to socket
        if (! empty( $this->getFrame('tmp') ) ) {

            $this->send( $this->getFrame('tmp'), true );            
            $this->setFrame('tmp', '');// Clear temporary data
        }
        
        // If Farme type are empty put default Frame type
        if ( empty( $this->getFrame('type') ) ) {

            $this->setFrame('type', Server::BINARY_TYPE_BLOB);
        }
       

    } 

    /**
     * Destroy connection.
     *
     * @return void
     */
    public function destroy(){
        
        // If Connection has Closed status exit
        if ( $this->status === self::STATUS_CLOSED ) {
            
            return;
        }

        // Remove all events listener for socket
        Stream::offSocket( $this->target );

        // Close socket
        Stream::FCLOSE( $this->target );
        
        // Unset Connection from Server connections array
        $this->server->unset( $this->id );

        // Put connection status Closed
        $this->status = self::STATUS_CLOSED;

        Emitter::dispatch( 'disconnected', $this, '[]' );

        Log::comment("Destroy was successfully", Log::LEVEL_DEBUG);
    }

    /**
     * Ping connection.
     *
     * @return void
     */
    public function ping(){
        
        $this->send( pack( 'H*', '8a00' ), true );
        Log::comment("Event Ping [". $this->token ."]", Log::LEVEL_DEBUG);
    }

    /**
     * Ping connection.
     *
     * @return void
     */
    public function pong(){
        
        Log::comment("Event Pong [". $this->token ."]", Log::LEVEL_DEBUG);
    }

     /**
     * Base write handler.
     *
     * @return void|bool
     */
    public function baseWrite(){

        if( Emitter::dispatch( 'message-sending', $this, $this->_sendb )->isPropagationStopped() ){

            return null;

        }else{
            // Write Send buffer to socket
            $len = Stream::FWRITE( $this->target, $this->_sendb, 8192) ;

            Emitter::dispatch( 'message-sended', $this, $this->_sendb );

            // If length of writed are the same length with send buffer remove write event listener
            if ( $len === strlen( $this->_sendb ) ) {

                Stream::off( 'write', $this->target );
                
                $this->_sendb = ''; // Clear send buffer
                
                // If Connection has status Closing destroy connection
                if ( $this->_status === self::STATUS_CLOSING ) {

                    $this->destroy();
                }

                return true;
            }

            // If length of sended are great than 0 clear send buffer part else destroy connection
            if ( $len > 0 ) {
                
                $this->_sendb = substr( $this->_sendb, $len );

            } else {
                
                $this->destroy();
            }
        }

    }

    public function __get( $property ) {

        $reflection = new ReflectionObject($this);

        if (
                $reflection->hasProperty( $property )
            &&  $reflection->getProperty( $property )->isPublic()
        ) {

            return $this->$property;

        }elseif( isset( $this->Assigned[ $property ] ) ){

            return $this->Assigned[ $property ];
        }
    }

    public function __set( $property, $value ) {
    
        $reflection = new ReflectionObject($this);

        if (
                $reflection->hasProperty( $property )
            &&  $reflection->getProperty( $property )->isPublic()
        ) {

            $this->$property = $value;

        }else{

            $this->Assigned[ $property ] = $value;
        }

        return $this;
    }

}