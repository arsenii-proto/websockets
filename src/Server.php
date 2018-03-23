<?php

namespace Arsenii\WebSockets;

use Arsenii\WebSockets\Facades\Stream;
use Arsenii\WebSockets\Facades\Servant;
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
    public function __construct($hostAddress = null, $hostPort = null, $hostPath = null, $hostProtocol = null, $hostDefaultServantName = null){
        Log::info('init server');

        if (php_sapi_name() != "cli") {
            exit("only run in command line mode \n");
        }
        
        $default = config('websockets');

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

            if(! is_null( $hostDefaultServantName ) )
                $default['servant'] = $hostDefaultServantName;

        }

        $this->setVars( $default );

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
        if( isset( $vars['host'] ) )
            $this->hostAddress      = $vars['host'];

        if( isset( $vars['port'] ) )
            $this->hostPort         = $vars['port'];

        if( isset( $vars['path'] ) )
            $this->hostPath         = $vars['path'];

        if( isset( $vars['protocol'] ) )
            $this->hostProtocol     = $vars['protocol'];

        if( isset( $vars['servants'] ) )
            $this->servants         = $vars['servants'];

        if( isset( $vars['servant'] ) ){
            $this->hostDefaultServantName = $vars['servant'];
            
            if( isset( $this->servants[ $this->hostDefaultServantName ] ) && is_array( $this->servants[ $this->hostDefaultServantName ] ) ){
                $this->hostServant  = (object) $this->servants[ $this->hostDefaultServantName ]; 
            }
        }

        if( isset( $vars['ssl'] ) ){
            if( isset( $vars['ssl'][''] ) ){
                $this->hostPrivateKey   = $vars['ssl'][''];
            }
            
            if( isset( $vars['ssl'][''] ) ){
                $this->hostCert         = $vars['ssl'][''];
            }
            
            if( isset( $vars['ssl'][''] ) ){
                $this->hostPassphrase   = $vars['ssl'][''];
            }
        }

    }

    /**
     * Start WebSockets Server instance.
     *
     * @return void
     */
    public function start(){
        Log::info('open server');
        $this->masterSocket = Stream::open(
            'tcp://' . $this->hostAddress .':'. $this->hostPort .'/'. $this->hostPath,
            (
                $this->hostProtocol == 'ws' ? null : [
                    'ssl' => [
                        'local_cert'    => $this->hostPrivateKey,
                        'local_pk'      => $this->hostCert,
                        'passphrase'    => $this->hostPassphrase,
                        'verify_peer'   => !1,
                    ]
                ]
            )
        );        

        Log::info('add listen server');

        $this->listen();        
        Servant::addListenners();
        Stream::loop();
    }

    /**
     * Assign Events to Looper.
     *
     * @return void
     */
    protected function listen(){

        Stream::on( 'read', $this->masterSocket, function($target, $data){

            Log::info("on stream read [{$target}] [{$data}]");
            $this->makeConection($target);
        });

    }

    /**
     * Accept new connection.
     *
     * @param  string  $target
     * @return void
     */
    protected function makeConection($target){   
        $connuid    = null;
        $new_target = Stream::accept($target);
        
        if (! $new_target ) {
            return;
        }


        while(
                ( $connuid = uniqid( count( $this->connections ) + 1, !0 ) ) != null
            &&  ( isset( $this->connections[ $connuid ] ) )
        ); 
        
        $this->connections[ $connuid ] = new Connection($connuid, $new_target, $this->hostProtocol, $this);

        Log::info( 'new Conn -> '. $connuid );
        // dd( 'Idem Curiti,', $this->connections[ $connuid ] );
    }

    /**
     * Unset connection.
     *
     * @param  string  $connuid
     * @return bool
     */
    public function unset($connuid){   
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
    public function getPackageLength($connuid){

        if ( isset( $this->connections[ $connuid ] ) ) {

            $connection = $this->connections[ $connuid ];
            $buff       = $connection->getBuffer();
            $bufflen    = $connection->getBufferLen();

            if ( $bufflen < 2 ) {

                // Log::comment('bufflen ['. $bufflen .']');
                return 0;
            }

            if( $connection->getStatus() < Connection::STATUS_HANDSHAKE_ESTABLISHED ){

                //Log::comment('handshake ['. $connuid .']');
                return $this->handshake($connuid);
            }

            if ( $connection->getFrame('curlen') ) {

                if ( $connection->getFrame('curlen') > $bufflen ) {

                    return 0;
                }

            } else {

                $first          = ord( $buff[0] );
                $second         = ord( $buff[1] );
                $datalen        = $second & 127;
                $is_fin_frame   = $first >> 7;
                $masked         = $second >> 7;
                $opcode         = $first & 0xf;

                switch ( $opcode ) {
                    case 0x0: break;
                    // Blob type.
                    case 0x1: break;
                    // Arraybuffer type.
                    case 0x2: break;
                    // Close package.
                    case 0x8:

                        $connection->close();

                        return 0;
                    // Ping package.
                    case 0x9:
                        
                        $connection->ping();

                        if (! $datalen ) {

                            $head_len   = $masked ? 6 : 2;
                            $connection->clearBuff( $head_len );

                            if ( $bufflen > $head_len ) {
                                
                                return $this->getPackageLength( $connuid );
                            }

                            return 0;
                        }

                        break;
                    // Pong package.
                    case 0xa:
                        
                        $connection->pong();
                        
                        //  Consume data from receive buffer.
                        if (! $datalen ) {

                            $head_len   = $masked ? 6 : 2;
                            $connection->clearBuff( $head_len );

                            if ( $bufflen > $head_len ) {

                                return $this->getPackageLength( $connuid );
                            }

                            return 0;
                        }

                        break;
                    // Wrong opcode. 
                    default :
                        Log::error( "error opcode $opcode and close websocket connection. Buffer:" . $connection->getBuffer(!0) );
                        $connection->close();
                        return 0;
                }

                // Calculate packet length.
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

                    Log::error("error package. package_length=$total_package_size");                    
                    $connection->close();
                    return 0;
                }

                if ($is_fin_frame) {

                    return $current_frame_length;

                } else {

                    $connection->setFrame( 'curlen', $current_frame_length );
                }
            }
            
        }

        Log::comment('no conn ['. $connuid .']');
        return 0;
    }

    /**
     * Make HandShake for connection.
     *
     * @param  string  $connuid
     * @return int
     */
    protected function handshake( $connuid ){   
        if ( isset( $this->connections[ $connuid ] ) ) {

            $connection = $this->connections[ $connuid ];
            $buff       = $connection->getBuffer();
            $bufflen    = strlen( $buff );

            if ( 0 === strpos( $buff, 'GET' ) ) {

                 //// Log::comment('handshake GET ['. $connuid .']');

                $endpos     = strpos($buff, "\r\n\r\n");
                $headlen    = $endpos + 4;
                $wskey      = '';

                if (! $endpos ) {

                     //Log::comment('handshake endpos ['. $connuid .']');
                    return 0;
                }

                if ( preg_match( "/Sec-WebSocket-Key: *(.*?)\r\n/i", $buff, $match ) ) {

                    //Log::comment('handshake match key ['. $match[1] .']');
                    $wskey = $match[1];

                } else {

                    $connection->send(  
                        "HTTP/1.1 400 Bad Request\r\n\r\n".
                        "<b>400 Bad Request</b><br>".
                        "Sec-WebSocket-Key not found.<br>".
                        "This is a WebSocket service and can not be accessed via HTTP.", 
                        true 
                    );

                    $connection->close();
                    return 0;
                }
                
                
                $ownkey = base64_encode( sha1( $wskey . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true ) );
                
                $upgrade = "HTTP/1.1 101 Switching Protocols\r\n";
                $upgrade .= "Upgrade: websocket\r\n";
                $upgrade .= "Sec-WebSocket-Version: 13\r\n";
                $upgrade .= "Connection: Upgrade\r\n";
                $upgrade .= "Server: arsenii/". Server::VERSION ."\r\n";
                $upgrade .= "Sec-WebSocket-Accept: ". $ownkey ."\r\n\r\n";
                
                //Log::comment('handshake start handshake ['. $upgrade .']');
                $connection->handshake( $upgrade, $headlen );

                if ( $bufflen > $headlen ) {

                    return $this->getPackageLength( substr( $buff, $headlen ) , $connection );
                } 

                return 0;

            }elseif ( 0 === strpos( $buff, '<polic' ) ) {
                
                $policy_xml     =   '<?xml version="1.0"?><cross-domain-policy><site-control permitted-cross-domain-policies="all"/>'.
                                    '<allow-access-from domain="*" to-ports="*"/></cross-domain-policy>' . "\0";

                $connection->send( $policy_xml, true );
                $connection->consumeRecvBuffer( strlen( $buff ) );

                return 0;
            }
            
            
            $connection->send(
                "HTTP/1.1 400 Bad Request\r\n\r\n".
                "<b>400 Bad Request</b><br>".
                "Invalid handshake data for websocket.",
                true
            );
            $connection->close();

            return 0;
            
        }

        //Log::comment('handshake no conn ['. $connuid .']');
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
        if (! is_scalar( $buff ) ) {

            throw new \Exception("You can't send(" . gettype( $buff ) . ") to client, you need to convert it to a string. ");
        }

        if ( isset( $this->connections[ $connuid ] ) ) {

            $connection = $this->connections[ $connuid ];
            $len        = strlen( $buff );

            if ( empty( $connection->getFrame('type') ) ) {

                $connection->setFrame( 'type', static::BINARY_TYPE_BLOB );
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

            if ( empty( $connection->getFrame('handshake') ) ) {

                if ( empty( $connection->getFrame('tmp') ) ) {

                    $connection->setFrame('tmp', '');
                }

                if ( strlen( $connection->getFrame('tmp') ) > Connection::MAX_SEND_BUFFER_SIZE ) {

                    //dispatch('onError')
                    
                    return '';
                }

                $connection->setFrame('tmp', $connection->getFrame('tmp') . $encode_buffer);

                
                if ( Connection::MAX_SEND_BUFFER_SIZE <= strlen( $connection->getFrame('tmp') ) ) {

                   //dispatch('bufer-full')

                }

                return '';
            }

            return $encode_buffer;
        }

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

        if( isset( $this->connections[ $connuid ] ) ){

            $masks      = null;
            $data       = null;
            $decoded    = null;
            $connection = $this->connections[ $connuid ];
            $len        = ord( $buff[1] ) & 127;

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

            if ( $connection->getFrame('curlen') ) {

                $connection->setFrame('buff', $connection->getFrame('buff') . $decoded );

                // Log::comment('return buf');
                return $connection->getFrame('buff');

            } else {

                if ( $connection->getFrame('buff') !== '' ) {

                    $decoded    = $connection->getFrame('buff') . $decoded;
                    $connection->setFrame('buff', '');
                }

                // Log::comment('decoded');
                return $decoded;
            }
        }

        // Log::comment('null');
        return null;        
    }


}