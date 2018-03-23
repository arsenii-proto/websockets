<?php

namespace Arsenii\WebSockets;



class Servant
{
    
    public function addListenners(){

        Stream::on( 'tick', function(){
            Log::info("on stream tick");
        });

    }

}