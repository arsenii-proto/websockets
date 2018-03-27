<?php

namespace Arsenii\WebSockets;

use Arsenii\WebSockets\Strem;
use Arsenii\WebSockets\Server;
use Arsenii\WebSockets\Log;

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

        $this->status   = static::STATUS_INITIAL;

        $meta = Stream::getMeta( $target );
        
        if(! $meta['timed_out'] ){

            $this->status   = static::STATUS_CONNECTING;
        }else{

            Log::error( Stream::lastErrorMessage( $target ) );
        }

        $this->address  = Stream::getAddress( $target );

        Stream::blocking( $target, !1 );

        Stream::readBufferPosition( $target, 0 );

        Log::info( 'new connection ['. $this->address .']' );
        
        Stream::on('read', $target, function(){
            $this->readBuffer();
        });
    }

    /**
     * Read socket Buffer.
     *
     * @return void
     */
    protected function readBuffer(){   
        if( 
                in_array( $this->type, [ 'wss', 'auto' ] )
            &&  $this->status < static::STATUS_HANDSHAKE_ESTABLISHED
        ){
            Log::comment('wss enter');
            $result = Stream::crypto( $this->target, !0 );
            
            if( false === $result ) {

                if (! Stream::FEOF( $this->target ) ) {

                    Log::error("SSL Handshake fail.\nBuffer:". Stream::FREAD( $this->target, 8182, !0 ));
                }

                return $this->destroy();

            } elseif( 0 === $result ) {

                return; // There isn't enough data and should try again.
            }
            
            Log::comment(" dispatch('onSslHandshake') ");
            // Log::comment('onSslHandshake ['. $this->target .']');
            
            $this->status = static::STATUS_HANDSHAKE_ESTABLISHED;

            $this->checkSend();

            return;
        }
        
        $buffer = Stream::buffer( $this->target, static::READ_BUFFER_SIZE );

        if( $buffer === '' || $buffer === false ){

            if( Stream::checkFEOF( $this->target, static::READ_BUFFER_SIZE ) ){

                Log::comment('destroy 1');
                return $this->destroy();
            }
            
        }


        $this->_buff    .= $buffer;
        $this->_buffl   += strlen( $buffer );

        while ($this->_buff !== '') {
            
            if ($this->_cpl) {

                 Log::comment('if');

                if ($this->_cpl > $this->_buffl ) {
                     Log::comment('break');
                    break;
                }

                 Log::comment('no break');

            } else {

                 Log::comment('else');
                
                $this->_cpl = $this->server->getPackageLength( $this->id );
                 Log::comment('_cpl ['. $this->_cpl .']');

                if ( 0 === $this->_cpl ) {

                     Log::comment('break ['. $this->_cpl .'] -> ');

                    break;

                } elseif (
                        $this->_cpl > 0
                    &&  $this->_cpl <= static::MAX_PACKAGE_SIZE
                ) {
                    
                    if ( $this->_cpl > $this->_buffl ) {

                        break;
                    }

                } else {

                    Log::error( 'error package. package_length='. var_export($this->_cpl, true) );
                    Log::comment('destroy 2');
                    $this->destroy();
                    return;
                }
            }

            if ( strlen( $this->_buff ) === $this->_cpl ) {
                
                $buffreq        = $this->_buff;
                $this->_buff    = '';

            } else {

                $buffreq        = substr( $this->_buff, 0, $this->_cpl );
                $this->_buff    = substr( $this->_buff, $this->_cpl );
            }
            
            $this->_cpl = 0;
            $message    = $this->server->decode( $this->id, $buffreq );

            Log::comment(" dispatch('onMessage') ");
            Log::comment('onMessage ['. $this->target .']['. $message .']');

            $this->send('Merci, '.$message);
        }
        
        Log::comment('onRead ['. $this->target .']');
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

        if ( static::MAX_SEND_BUFFER_SIZE <= strlen( $this->_sendb ) + strlen( $data ) ) {
            
            Log::comment(" dispatch('onError') ");

            return false;
        }

        $this->_sendb .= $data;
        
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

        Log::comment('handshake -> handshake start send ['. $data .']');

        if (
                $this->status === static::STATUS_CLOSING
            ||  $this->status === static::STATUS_CLOSED
        ) {

            //Log::comment('handshake -> handshake send close ['. $this->status .']');
            return false;
        }
        
        if ( false === $raw ) {
            
            $data   = $this->server->encode( $this->id, $data );

            if ($data === '') {

                Log::comment('handshake -> handshake send data null ['. $data .']');
                return null;
            }
        }

        if ( $this->status < static::STATUS_ESTABLISHED ) {

            Log::comment('handshake -> handshake send buff ['. $data .']');
            $this->putSend( $data );

            return null;
        }

        //Log::comment('handshake -> handshake ['. $this->_sendb .']');

        if ( $this->_sendb === '' ) {

            Log::comment('handshake -> handshake send ['. $data .']');

            $len = Stream::FWRITE( $this->target, $data, 8192 );

            if ( $len === strlen( $data ) ) {

                return true;
            }

            
            if ( $len > 0 ) {

                $this->putSend( substr($data, $len) );

            } else {

                if (! Stream::alive( $this->target ) ) {

                    Log::comment(" dispatch('onError') ");
                    $this->destroy();

                    return false;
                }

                $this->putSend( $data );
            }

            // Worker::$globalEvent->add($this->_socket, EventInterface::EV_WRITE, array($this, 'baseWrite'));
            Stream::on( 'write', $this->target, function(){
    
                $this->baseWrite();
            });

            return null;

        } else {

            $this->putSend( $data );
        }
    }

    /**
     * Check if there are data to send.
     *
     * @return void
     */
    public function checkSend(){

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

        if( is_null( $len ) )
            $len = strlen( $this->_buff );

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
        if (
                $this->status === static::STATUS_CLOSING
            ||  $this->status === static::STATUS_CLOSED
        ) {

            return;

        } else {

            if (! is_null( $data ) ) {

                $this->send( $data, $raw );
            }

            $this->status = static::STATUS_CLOSING;
        }

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

        $this->status = static::STATUS_HANDSHAKE_ESTABLISHED;
        
        $this->setFrame('buff', '');
        $this->setFrame('curlen', 0);
        $this->setFrame('curbuff','');
        $this->setFrame('handshake', true);

        $this->clearBuff( $headlen );
        
        $this->status = static::STATUS_ESTABLISHED;

        Log::comment($upgrade);
        
        $this->send( $upgrade, true );
        
        if (! empty( $this->getFrame('tmp') ) ) {

            $this->send( $this->getFrame('tmp'), true );            
            $this->setFrame('tmp', '');
        }
        
        if ( empty( $this->getFrame('type') ) ) {

            $this->setFrame('type', Server::BINARY_TYPE_BLOB);
        }
        // Try to emit onWebSocketConnect callback.
        // if ( isset($connection->onWebSocketConnect) || isset($connection->worker->onWebSocketConnect)) {
        //     static::parseHttpHeader($buffer);
        //     try {
        //         call_user_func(isset($connection->onWebSocketConnect)?$connection->onWebSocketConnect:$connection->worker->onWebSocketConnect, $connection, $buffer);
        //     } catch (\Exception $e) {
        //         Worker::log($e);
        //         exit(250);
        //     } catch (\Error $e) {
        //         Worker::log($e);
        //         exit(250);
        //     }
        //     if (!empty($_SESSION) && class_exists('\GatewayWorker\Lib\Context')) {
        //         $connection->session = \GatewayWorker\Lib\Context::sessionEncode($_SESSION);
        //     }
        //     $_GET = $_SERVER = $_SESSION = $_COOKIE = array();
        // }

    } 

    /**
     * Destroy connection.
     *
     * @return void
     */
    public function destroy(){
        
        if ( $this->status === static::STATUS_CLOSED ) {
            
            return;
        }

        Stream::offSocket( $this->target );
        Stream::FCLOSE( $this->target );
        
        $this->server->unset( $this->id );

        $this->status = static::STATUS_CLOSED;

        Log::comment(" dispatch('onClose') ");
    }

    /**
     * Ping connection.
     *
     * @return void
     */
    public function ping(){
        
        $this->send( pack( 'H*', '8a00' ), true );
        Log::comment(" dispatch('onPing') ");
    }

    /**
     * Ping connection.
     *
     * @return void
     */
    public function pong(){
        
        // $this->send( pack( 'H*', '8a00' ), true );
        Log::comment(" dispatch('onPing') ");
    }

    /**
     * Destruct.
     *
     * @return void
     */
    public function __destruct(){
        // static $mod;

        // if ( Worker::getGracefulStop() ) {

        //     if (! isset( $mod ) ) {

        //         $mod = ceil( ( self::$statistics['connection_count'] + 1 ) / 3 );
        //     }

        //     if (0 === self::$statistics['connection_count'] % $mod) {
        //         Worker::log('worker[' . posix_getpid() . '] remains ' . self::$statistics['connection_count'] . ' connection(s)');
        //     }

        //     if(0 === self::$statistics['connection_count']) {
        //         Worker::$globalEvent->destroy();
        //         exit(0);
        //     }
        // }
    }

     /**
     * Base write handler.
     *
     * @return void|bool
     */
    public function baseWrite()
    {
        $len = Stream::FWRITE( $this->target, $this->_sendb, 8192) ;

        if ( $len === strlen( $this->_sendb ) ) {

            Stream::off( 'write', $this->target );
            
            $this->_sendb = '';
            
            if ( $this->_status === static::STATUS_CLOSING ) {

                $this->destroy();
            }

            return true;
        }

        if ( $len > 0 ) {

            $this->_sendb = substr( $this->_sendb, $len );

        } else {
            
            $this->destroy();
        }
    }

}