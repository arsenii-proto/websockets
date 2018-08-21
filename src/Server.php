<?php

namespace Arsenii\WebSockets;

use Arsenii\WebSockets\Facades\Stream;
use Arsenii\WebSockets\Facades\Servant;
use Arsenii\WebSockets\Facades\Emitter;
use Arsenii\WebSockets\Connection;

use Arsenii\WebSockets\Log;

class Server
{
     /**
     * Version.
     *
     * @var string
     */
    const VERSION = '2.0.1';

    /**
     * Status starting.
     *
     * @var int
     */
    const STATUS_STARTING = 1;

    /**
     * Status running.
     *
     * @var int
     */
    const STATUS_RUNNING = 2;

    /**
     * Status shutdown.
     *
     * @var int
     */
    const STATUS_SHUTDOWN = 4;

    /**
     * Websocket blob type.
     *
     * @var string
     */
    const BINARY_TYPE_BLOB = "\x81";
    
    /**
     * The backlog for WebSockets Server.
     *
     * @var string
     */
    protected $backlog;
    
    /**
     * The host address for WebSockets Server.
     *
     * @var string
     */
    protected $hostAddress;
    
    /**
     * The host port for WebSockets Server.
     *
     * @var string
     */
    protected $hostPort;
    
    /**
     * The host path for WebSockets Server.
     *
     * @var string
     */
    protected $hostPath;
    
    /**
     * The host protocol for WebSockets Server.
     *
     * @var string
     */
    protected $hostProtocol;
    
    /**
     * The host default servant name for WebSockets Server.
     *
     * @var string
     */
    protected $hostDefaultServantName;
    
    /**
     * The host servant for WebSockets Server.
     *
     * @var array
     */
    protected $hostServant;
    
    /**
     * The host private key for WebSockets Server.
     *
     * @var string
     */
    protected $hostPrivateKey;
    
    /**
     * The host certificate for WebSockets Server.
     *
     * @var string
     */
    protected $hostCert;
    
    
    /**
     * The host passphrase for WebSockets Server.
     *
     * @var string
     */
    protected $hostPassphrase;
    
    /**
     * The master socket for WebSockets Server.
     *
     * @var mixed
     */
    protected $masterSocket;

    /**
     * The array of servants.
     *
     * @var array
     */
    protected $servants = [];

    /**
     * The array of connections.
     *
     * @var array
     */
    protected $connections = [];

    /**
     * Current status.
     *
     * @var int
     */
    protected static $_status = self::STATUS_STARTING;
    
    /**
     * OS.
     *
     * @var string
     */
    protected static $_OS = 'linux';

    /**
     * Create a new WebSockets Server instance.
     *
     * @param  string|array|null    $hostAddress
     * @param  string|null          $hostPort
     * @param  string|null          $hostPath
     * @param  string|null          $hostProtocol
     * @param  string|null          $hostDefaultServantName
     * @return void
     */
    public function __construct($hostAddress = null, $hostPort = null, $hostPath = null, $hostProtocol = null){
        
        Log::info('init server', Log::LEVEL_DEBUG);

        // Allow only CLI Mode
        if (php_sapi_name() != "cli") {

            exit("only run in command line mode \n");
        }
        
        // Import configs from config_path('websockets.php')
        $default = config('websockets');

        // hostAddress argument can be passed like array
        if( is_array( $hostAddress ) ){

            $default = array_merge( $default, $hostAddress );

        }else{

            if(! is_null( $hostAddress ) )
                $default['host'] = $hostAddress;

            if(! is_null( $hostPort ) )
                $default['port'] = $hostPort;

            if(! is_null( $hostPath ) )
                $default['path'] = $hostPath;

            if(! is_null( $hostProtocol ) )
                $default['protocol'] = $hostProtocol;

        }

        // Set Server variable from default array formed
        $this->setVars( $default );

        $this->backlog = md5(
            ( $this->hostProtocol != "auto" ? $this->hostProtocol : '(wss?)' ).
            '://'.
            $this->hostAddress.
            ':'.
            $this->hostPort.
            ( substr( $this->hostPath, 0, 1 ) == '/' ? '' : '/' ).
            $this->hostPath
        );
        
        // Check OS 
        if (DIRECTORY_SEPARATOR === '\\') {
            self::$_OS = 'windows';
        }

    }

    /**
     * Assing vars to a newly WebSockets Server instance.
     *
     * @param  array|null  $vars
     * @return void
     */
    protected function setVars($vars = []){
        
        if( isset( $vars['host'] ) ){

            $this->hostAddress      = $vars['host'];
        }

        if( isset( $vars['port'] ) ){

            $this->hostPort         = $vars['port'];
        }

        if( isset( $vars['path'] ) ){

            $this->hostPath         = $vars['path'];
        }

        if( isset( $vars['protocol'] ) ){

            $this->hostProtocol     = $vars['protocol'];
        }

        if( isset( $vars['ssl'] ) ){

            if( isset( $vars['ssl']['local_private_key'] ) ){
                
                $this->hostPrivateKey   = $vars['ssl']['local_private_key'];
            }
            
            if( isset( $vars['ssl']['local_certificate'] ) ){
                
                $this->hostCert         = $vars['ssl']['local_certificate'];
            }
            
            if( isset( $vars['ssl']['passphrase'] ) ){
                
                $this->hostPassphrase   = $vars['ssl']['passphrase'];
            }
        }

    }

    /**
     * Start WebSockets Server instance.
     *
     * @return void
     */
    public function start(){

        Log::info('open server', Log::LEVEL_DEBUG);

        $address                = $this->hostAddress .':'. $this->hostPort .( substr( $this->hostPath, 0, 1 ) == '/' ? '' : '/' ). $this->hostPath;
        $context_options        = null;

        // If protocol not ws enable ssl
        if( $this->hostProtocol != 'ws' ){

            $context_options    = [
                    'ssl' => [
                        'local_cert'    => $this->hostCert,
                        'local_pk'      => $this->hostPrivateKey,                        
                        'verify_peer'   => !1,
                    ]
                ];
            
            // If `passphrase` was added push it in context
            if(! empty( $this->hostPassphrase ) ){

                $context_options['ssl']['passphrase'] = $this->hostPassphrase;
            }

        }

        // Open websockets stream server
        $this->masterSocket     = Stream::open( 'tcp://'. $address, $context_options );

        Log::info("Server [ ". ( $this->hostProtocol != "auto" ? $this->hostProtocol : '(wss?)' ) .'://'. $address ." ] are started");

        $this->clearBacklog();

        // Register event read on stream 
        Stream::on( 'read', $this->masterSocket, function($target, $data){

            Log::info("on stream read [{$target}] [{$data}]", Log::LEVEL_DEBUG);

            // Create Connection 
            $this->makeConection($target);
        });

        // Register event read on stream 
        Stream::on( 'tick', function(){

            $this->checkBacklog();
        });

        // Load all Listeners from base_path('routes/websockets.php')
        Emitter::addListeners();

        // Start Stream looper
        Stream::loop();
    }

    /**
     * Accept new connection.
     *
     * @param  string  $target
     * @return void
     */
    protected function makeConection($target){

        $connuid    = null;

        // Accept socket on the stream
        $new_target = Stream::accept($target);
        
        // If socket wasn't accepted
        if (! $new_target ) {
            return;
        }

        // Generate uniqid for new Connection
        while(
                ( $connuid = uniqid( count( $this->connections ) + 1, !0 ) ) != null
            &&  ( isset( $this->connections[ $connuid ] ) )
        ); 
        
        // Assign New Connection to server connections array with uniqid key
        $connection = new Connection($connuid, $new_target, $this->hostProtocol, $this);

        if( $connection->getStatus() != Connection::STATUS_CLOSED ) {

            $this->connections[ $connuid ] = $connection;
            Emitter::dispatch( 'connected', $connection, '[]' );
        }

        Log::info( 'new Conn -> '. $connuid, Log::LEVEL_DEBUG);

    }

    /**
     * Unset connection.
     *
     * @param  string  $connuid
     * @return bool
     */
    public function unset($connuid){

        // Check Connection uniqid presence
        if ( isset( $this->connections[ $connuid ] ) ) {

            unset( $this->connections[ $connuid ] );
            
        }

        return true;
    }

    /**
     * Get Current package Length for connection.
     *
     * @param  string  $connuid
     * @return int
     */
    public function packlen($connuid){

        global $AD_COUNTER;

        $AD_COUNTER = $AD_COUNTER ?? 0;
        $AD_COUNTER++;


        Log::comment("-------------------\n--------- START - $AD_COUNTER \n-------------------", Log::LEVEL_DEBUG);

        // Check Connection uniqid presence
        if ( isset( $this->connections[ $connuid ] ) ) {

            // Take connection
            $connection = $this->connections[ $connuid ];
            
            // Take buffer from connection
            $buff       = $connection->getBuffer();
            
            // Take buffer length from connection
            $bufflen    = $connection->getBufferLen();
            Log::comment('Receive length. '. $bufflen, Log::LEVEL_DEBUG);

            // Data offset (4 bits) ( take a look https://en.wikipedia.org/wiki/Transmission_Control_Protocol#TCP_segment_structure )
            if ( $bufflen < 6 ) {

                Log::comment('We need more data ['. $bufflen .']', Log::LEVEL_DEBUG);
                return 0;
            }

            // Log::comment('Conn Status ->'. $connection->getStatus(), Log::LEVEL_DEBUG);

            if( $connection->getStatus() < Connection::STATUS_ESTABLISHED ){

                Log::comment('Has not yet completed the handshake. ', Log::LEVEL_DEBUG);
                
                // Perform Handshake ( take a look https://en.wikipedia.org/wiki/WebSocket#Protocol_handshake )
                return $this->handshake($connuid);
            }
            
            // When connection has websockets Frame length
            if ( $connection->getFrame('curlen') ) {

                // Log::info("if");

                Log::comment('Buffer websocket frame data. ', Log::LEVEL_DEBUG);
                
                if ( $connection->getFrame('curlen') > $bufflen ) {
                    
                    Log::comment('We need more frame data. Return 0, because it is not clear the full packet length, waiting for the frame of fin=1.', Log::LEVEL_MASTER);
                    return 0;
                }

            } else {

                $first          = ord( $buff[0] ); // Take firsts 2 bits
                $second         = ord( $buff[1] ); // Take seconds 2 bits
                $datalen        = $second & 127; // Get data length
                $is_fin_frame   = $first >> 7; // Check if is finish of frame ( segment )
                $masked         = $second >> 7; // Check if frame ( segment ) was masked
                $opcode         = $first & 0xf; // Get opcode ( take a look https://tools.ietf.org/html/rfc6455#page-65 )

                if (! $masked ) {
                    
                    Log::comment("frame not masked so close the connection\n", Log::LEVEL_DEBUG);
                    $connection->close();
                    return 0;
                }

                switch ( $opcode ) {

                    case 0x0: 
                        Log::info("0x0 type. ");
                        break;

                    case 0x1: 
                        // Blob type.
                        Log::comment('Blob type. ', Log::LEVEL_DEBUG);
                        break;
                    case 0x2: 
                        // Arraybuffer type.
                        Log::comment('Arraybuffer type. ', Log::LEVEL_DEBUG);
                        break;
                    case 0x8:
                        // Connection Close Frame
                        Log::comment('Close package. ', Log::LEVEL_DEBUG);
                        $connection->close();
                        return 0;
                    case 0x9:
                        // // Ping package.
                        Log::comment('Ping package. ', Log::LEVEL_DEBUG);
                        // $connection->ping();

                        // //  Consume data from receive buffer.
                        // if (! $datalen ) {

                        //     $head_len   = $masked ? 6 : 2;
                        //     $connection->clearBuff( $head_len );

                        //     if ( $bufflen > $head_len ) {
                                
                        //         return $this->packlen( $connuid );
                        //     }

                        //     return 0;
                        // }

                        break;
                    case 0xa:
                        // Pong package.
                        Log::comment('Pong package.', Log::LEVEL_DEBUG);
                        // $connection->pong();
                        
                        // //  Consume data from receive buffer.
                        // if (! $datalen ) {

                        //     $head_len   = $masked ? 6 : 2;
                        //     $connection->clearBuff( $head_len );

                        //     if ( $bufflen > $head_len ) {

                        //         return $this->packlen( $connuid );
                        //     }

                        //     return 0;
                        // }

                        break;
                    
                    default :
                        // Wrong opcode. 
                        Log::error( "Error opcode `$opcode` and close websocket connection. ");
                        $connection->close();
                        return 0;
                }

                // Calculate packet length.
                Log::comment('Calculate packet length. ', Log::LEVEL_DEBUG);
                $head_len       = 6;

                if ( $datalen === 126 ) {

                    $head_len   = 8;

                    if ( $head_len > $bufflen ) {

                        return 0;
                    }

                    $pack       = unpack( 'nn/ntotal_len', $buff );
                    $datalen    = $pack['total_len'];

                } else {

                    if ( $datalen === 127 ) {

                        $head_len   = 14;

                        if ( $head_len > $bufflen ) {
                            
                            return 0;
                        }

                        $arr        = unpack('n/N2c', $buff );
                        $datalen    = $arr['c1']*4294967296 + $arr['c2'];
                    }
                }

                $current_frame_length = $head_len + $datalen;

                $total_package_size = strlen( $connection->getFrame('buff') ) + $current_frame_length;

                if ( $total_package_size > Connection::MAX_PACKAGE_SIZE ) {

                    Log::error("Error package. package_length = $total_package_size");

                    $connection->close();

                    return 0;
                }

                if ($is_fin_frame) {

                    if ( 0x9 === $opcode ) {

                        if ( $bufflen >= $current_frame_length ) {

                            $ping_data = $this->decode( $connuid, substr( $buff, 0, $current_frame_length ) );

                            $connection->clearBuff( $current_frame_length );

                            $tmp_connection_type = $connection->getFrame("type") ? $connection->getFrame("type") : self::BINARY_TYPE_BLOB;

                            $connection->setFrame("type", "\x8a");

                            $connection->send($ping_data, true);

                            $connection->setFrame("type", $tmp_connection_type);

                            if ( $bufflen > $current_frame_length ) {

                                $connection->clearBuff($current_frame_length);

                                return $this->packlen( $connuid );
                            }
                        }

                        return 0;

                    } else if ( 0xa === $opcode ) {

                        if ( $bufflen >= $current_frame_length ) {

                            $pong_data = $this->decode( $connuid, substr( $buffer, 0, $current_frame_length ) );

                            $connection->clearBuff( $current_frame_length );

                            $tmp_connection_type = $connection->getFrame("type") ? $connection->getFrame("type") : self::BINARY_TYPE_BLOB;

                            $connection->setFrame("type", $tmp_connection_type);
                            
                            if ( $bufflen > $current_frame_length ) {

                                $connection->clearBuff($current_frame_length);

                                return $this->packlen( $connuid );
                            }
                        }

                        return 0;
                    }

                    return $current_frame_length;

                } else {
                    
                    // Push websockets Frame length
                    $connection->setFrame( 'curlen', $current_frame_length );
                }
                
            }

            // Received just a frame length data.
            Log::comment("Received just a frame length data. ", Log::LEVEL_DEBUG);

            if ( $connection->getFrame( 'curlen' ) === $bufflen ) {

                $this->decode( $connuid, $buff );

                $connection->clearBuff( $connection->getFrame( 'curlen' ) );

                $connection->setFrame( 'curlen', 0 );

                return 0;

            } // The length of the received data is greater than the length of a frame.
            elseif ( $connection->getFrame( 'curlen' ) < $bufflen ) {

                Log::comment("The length of the received data is greater than the length of a frame. ", Log::LEVEL_DEBUG);

                $this->decode( $connuid, substr( $buff, 0, $connection->getFrame( 'curlen' ) ) );

                $connection->clearBuff( $connection->getFrame( 'curlen' ) );

                // $current_frame_length = $connection->getFrame( 'curlen' );

                $connection->setFrame( 'curlen', 0 );
                // Continue to read next frame.
                Log::comment("Continue to read next frame. ", Log::LEVEL_DEBUG);

                // $connection->clearBuff($current_frame_length);

                return $this->packlen( $connuid );
            } // The length of the received data is less than the length of a frame.
            else {

                Log::comment("The length of the received data is less than the length of a frame. ", Log::LEVEL_DEBUG);

                return 0;
            }
            
        }

        // Connection with uniqid not present
        Log::comment('No conn ['. $connuid .']', Log::LEVEL_DEBUG);

        return 0;
    }

    /**
     * Make HandShake for connection.
     * 
     * source https://en.wikipedia.org/wiki/WebSocket#Protocol_handshake
     *
     * @param  string  $connuid
     * @return int
     */
    protected function handshake( $connuid ){

        // Check Connection uniqid presence
        if ( isset( $this->connections[ $connuid ] ) ) {

            // Take connection
            $connection = $this->connections[ $connuid ];

            // Take buffer from connection
            $buff       = $connection->getBuffer();

            // Take buffer length from connection
            $bufflen    = strlen( $buff );

            if ( 0 === strpos( $buff, 'GET' ) ) {

                Log::comment('Handshake GET ['. $connuid .']', Log::LEVEL_DEBUG);

                $endpos     = strpos($buff, "\r\n\r\n"); // Take End position of Headers
                $headlen    = $endpos + 4; // Get Length of Headers
                $wskey      = '';

                if (! $endpos ) {

                    Log::comment('Handshake not endpos ['. $connuid .']', Log::LEVEL_DEBUG);
                    return 0;
                }

                // If Header Sec-WebSocket-Key exists
                if ( preg_match( "/Sec-WebSocket-Key: *(.*?)\r\n/i", $buff, $match ) ) {

                    Log::comment('Handshake match key ['. $match[1] .']', Log::LEVEL_DEBUG);

                    // Put Header Sec-WebSocket-Key Value in wskey variable
                    $wskey = $match[1];

                } else {

                    // If Header Sec-WebSocket-Key not exists
                    $connection->send(  
                        "HTTP/1.1 400 Bad Request\r\n\r\n".
                        "<b>400 Bad Request</b><br>".
                        "Sec-WebSocket-Key not found.<br>".
                        "This is a WebSocket service and can not be accessed via HTTP.", 
                        true // As RAW
                    );

                    $connection->close();
                    return 0;
                }
                
                // Generate own key by Header Sec-WebSocket-Key Value
                $ownkey = base64_encode( sha1( $wskey . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true ) );
                
                // Standart Response Heders
                $upgrade = "HTTP/1.1 101 Switching Protocols\r\n";
                $upgrade .= "Upgrade: websocket\r\n";
                $upgrade .= "Sec-WebSocket-Version: 13\r\n";
                $upgrade .= "Connection: Upgrade\r\n";
                // $upgrade .= "Server: arsenii/". Server::VERSION ."\r\n"; // Put Server Version in response
                $upgrade .= "Sec-WebSocket-Accept: ". $ownkey ."\r\n\r\n"; // Put own key in response
                
                Log::comment('Handshake send Response ['. $upgrade .']', Log::LEVEL_DEBUG);

                // Send Handshake Response and header length to connection for perform Handshake action
                $connection->handshake( $upgrade, $headlen );

                if ( $bufflen > $headlen ) {

                    return $this->packlen( substr( $buff, $headlen ) , $connection );
                } 

                return 0;

            }elseif ( 0 === strpos( $buff, '<polic' ) ) {
                
                // Standart xml policy response
                $policy_xml     =   '<?xml version="1.0"?><cross-domain-policy><site-control permitted-cross-domain-policies="all"/>'.
                                    '<allow-access-from domain="*" to-ports="*"/></cross-domain-policy>' . "\0";

                // Send Handshake Response and header length to connection for perform Handshake action
                $connection->handshake( $policy_xml, strlen( $buff ) );

                return 0;
            }
            
            // If Header Protocol not allowed
            $connection->send(
                "HTTP/1.1 400 Bad Request\r\n\r\n".
                "<b>400 Bad Request</b><br>".
                "Invalid handshake data for websocket.",
                true // As RAW
            );
            $connection->close();

            return 0;
            
        }

        // Connection with uniqid not present
        Log::comment('Handshake no connection ['. $connuid .']', Log::LEVEL_DEBUG);
        return 0;
    }

    /**
     * Encode socket message.
     *
     * @param string    $connuid
     * @param string    $buff
     * @return string
     */
    public function encode( $connuid, $buff ){
        
        // If aren't scalar data type
        if (! is_scalar( $buff ) ) {

            throw new \Exception("You can't send(" . gettype( $buff ) . ") to client, you need to convert it to a string. ");
        }

        // Check Connection uniqid presence
        if ( isset( $this->connections[ $connuid ] ) ) {
            
            // Take connection
            $connection = $this->connections[ $connuid ];
            
            // Take buffer length from connection
            $len        = strlen( $buff );

            // If frame type are empty put default frame type
            if ( empty( $connection->getFrame('type') ) ) {

                $connection->setFrame( 'type', self::BINARY_TYPE_BLOB );
            }

            $first_byte = $connection->getFrame('type');

            if ( $len <= 125 ){

                $encode_buffer = $first_byte . chr($len) . $buff;

            } else {

                if ( $len <= 65535 ){

                    $encode_buffer = $first_byte . chr(126) . pack( "n", $len ) . $buff;

                } else {

                    $encode_buffer = $first_byte . chr(127) . pack( "xxxxN", $len ) . $buff;
                }
            }

            // Check if Frame Handshake aren't assigned
            if ( empty( $connection->getFrame('handshake') ) ) {

                // If frame tmp data are empty put default frame tmp data
                if ( empty( $connection->getFrame('tmp') ) ) {

                    $connection->setFrame('tmp', '');
                }

                if ( strlen( $connection->getFrame('tmp') ) > Connection::MAX_SEND_BUFFER_SIZE ) {

                    // dispatch('onError')
                    
                    Log::comment('Encode Temporary Data Length bigest than `Connection MAX_SEND_BUFFER_SIZE` ('.Connection::MAX_SEND_BUFFER_SIZE.')', Log::LEVEL_DEBUG);

                    return '';
                }

                // Push to Frame temporary data
                $connection->setFrame('tmp', $connection->getFrame('tmp') . $encode_buffer);

                
                if ( Connection::MAX_SEND_BUFFER_SIZE <= strlen( $connection->getFrame('tmp') ) ) {

                    Log::comment('Encode Concatenated Temporary Data Length bigest than `Connection MAX_SEND_BUFFER_SIZE` ('.Connection::MAX_SEND_BUFFER_SIZE.')', Log::LEVEL_DEBUG);
                   //dispatch('bufer-full')

                }

                Log::comment('Encode Encoded Data was assigned in Frame tmp data', Log::LEVEL_DEBUG);
                return '';
            }

            // Return Encoded Data
            return $encode_buffer;
        }


        // Connection with uniqid not present
        Log::comment('Encode no connection ['. $connuid .']', Log::LEVEL_DEBUG);
        return '';
    }

    /**
     * Decode socket message.
     *
     * @param string    $connuid
     * @param string    $buff
     * @return string
     */
    public function decode( $connuid, $buff ){

        // Check Connection uniqid presence
        if( isset( $this->connections[ $connuid ] ) ){

            $masks      = null;
            $data       = null;
            $decoded    = null;

            // Take connection
            $connection = $this->connections[ $connuid ];

            $len        = ord( $buff[1] ) & 127; // Get data length

            if ( $len === 126 ) {

                $masks  = substr( $buff, 4, 4 );
                $data   = substr( $buff, 8 );

            } else {

                if ( $len === 127 ) {

                    $masks  = substr( $buff, 10, 4 );
                    $data   = substr( $buff, 14 );

                } else {

                    $masks  = substr( $buff, 2, 4 );
                    $data   = substr( $buff, 6 );
                }
            }

            for ($i = 0; $i < strlen( $data ); $i++) 
                $decoded    .= $data[$i] ^ $masks[$i % 4];

            // If Frame data length push decoded data to buffer, and return all buffer
            if ( $connection->getFrame('curlen') ) {

                $connection->setFrame('buff', $connection->getFrame('buff') . $decoded );

                Log::comment('Decode push data in buff with returning', Log::LEVEL_DEBUG);
                return $connection->getFrame('buff');

            } else {

                // IF buffer aren't empty push buffer to decoded, after clear buffer
                if ( $connection->getFrame('buff') !== '' ) {

                    Log::comment('Decode push buff in decoded', Log::LEVEL_DEBUG);
                    $decoded    = $connection->getFrame('buff') . $decoded;
                    $connection->setFrame('buff', '');
                }

                Log::comment('Decode returning -> '. $decoded, Log::LEVEL_DEBUG);
                return $decoded;
            }
        }

        // Connection with uniqid not present
        Log::comment('Encode no connection ['. $connuid .']', Log::LEVEL_DEBUG);
        return null;        
    }

    public function clearBacklog(){
        
        if(! is_dir( __DIR__."/Backlog" ) )
            mkdir( __DIR__."/Backlog" );

        touch( __DIR__."/Backlog/{$this->backlog}.log" );

        unlink( __DIR__."/Backlog/{$this->backlog}.log" );

        if( is_dir( sys_get_temp_dir() ."/{$this->backlog}" ) ){
            
            array_map('unlink', glob(sys_get_temp_dir() ."/{$this->backlog}/*.*"));
            rmdir( sys_get_temp_dir() ."/{$this->backlog}" );
        }
    }

    public function checkBacklog(){

        if(! is_dir( __DIR__."/Backlog" ) )
            mkdir( __DIR__."/Backlog" );

        touch( __DIR__."/Backlog/{$this->backlog}.log" );

        $lines  = file( __DIR__."/Backlog/{$this->backlog}.log", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

        foreach( $lines as $index => $line ){
            
            if( substr($line, -10) === ' [checked]' )
                continue;

            $message = file_get_contents( $line );

            Emitter::dispatch( 'backlog', null, $message );

            $lines[ $index ] = $line .' [checked]';
            
            unlink( $line );
        }

        file_put_contents( __DIR__."/Backlog/{$this->backlog}.log", implode("\n", $lines) );
        
    }

    public function putBacklog( string $data = '' ){

        if(! is_dir( __DIR__."/Backlog" ) )
            mkdir( __DIR__."/Backlog" );
        
        if(! is_dir( sys_get_temp_dir() ."/{$this->backlog}" ) ){
            
            mkdir( sys_get_temp_dir() ."/{$this->backlog}" );
        }

        $tmp = null;

        while(
                ($tmp = sys_get_temp_dir() ."/{$this->backlog}/". uniqid($this->backlog) .'.log' ) != null
            &&  file_exists( $tmp )
        );

        file_put_contents( $tmp, $data );

        $backlog = fopen(__DIR__."/Backlog/{$this->backlog}.log", 'a');

        fwrite($backlog, "\n".$tmp);

        fclose($backlog);

    }

    static public function instance( $address = null, $port = null, $path = null, $protocol = null ){

        return new self( $address, $port, $path, $protocol );
    }

    public function getConnections(){

        return $this->connections;
    }
}