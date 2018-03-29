<?php

namespace Arsenii\WebSockets;

class DataResolver
{
    
    private static $varTypes = [ 
        "null", 
        "num", 
        "number", 
        "numeric", 
        "int", 
        "integer", 
        "float", 
        "bool", 
        "boolean", 
        "string", 
        "array", 
        "object" 
    ];


    public static function parse( $data = null){
    
        return @json_decode( $data );
    }

    public static function get( $data = null, $flow = null, $default = null){
        
        if( $flow == '*' ){
            return $data;
        }

        if( isset( $data->{$flow} ) ){

            return $data->{$flow};

        }else 
        
        if(
                !empty( trim( $flow ) )
            &&  ( $parts = explode('.', $flow) ) != null
            &&  count( $parts ) > 0 
        ){
            $last = $data;

            foreach (explode('.', $flow) as $part) {

                if(
                        empty( trim( $part ) )
                    ||  is_null( $last ) 
                )
                    continue;

                if( isset( $last->{$part} ) ){

                    $last = $last->{$part};

                }else{

                    return $default;
                }
                
            }

            return $last;
        }

        return $default;
    }

    public static function has( $data = null, $flow = null){
        
        $val = uniqid();
    
        return self::get($data, $flow, $val) !== $val;
    }

    public static function match( $data = null, $pattern = null){
        
        $flows = [];
        $match = 1;

        if( is_null( $pattern ) )
            return false;

        if( $pattern === '*' )
            return true;

        foreach (explode('&&', $pattern) as $flow) {

            if( empty( trim( $flow ) ) )
                continue;

            $val        = null;
        
            if(
                    ( $parts = explode('=', $flow) ) != null
                &&  ( count( $parts ) > 1 )
            ){

                if( empty( trim( $parts[0] ) ) )
                    continue;

                $flow   = trim( $parts[0] );
                $val    = trim( $parts[1] );
            }

            if( is_null( $val ) ){

                $flows[] = [
                    
                    "var" => $flow 
                ];

            }else{

                $flows[] = [

                    "var" => $flow,
                    "val" => $val
                ];
            }

        }

        foreach ( $flows as $flow) {

            if( self::has( $data, $flow['var']) ){

                if( isset( $flow['val'] ) ){                    

                    $match *= ( self::matchValue( $data, self::get( $data, $flow['var'] ), $flow['val'] ) ? 1 : 0 );

                }else{
                
                    $match *= 1;
                }

            }else{

                $match = 0;
            }
        }

        return $match != 0;
    }

    private static function matchValue( $data, $val, $pattern ){

        if(
                (
                        substr( $pattern, 0, 1 ) === ':'
                    ||  substr( $pattern, 0, 1 ) === '!' 
                )
            &&  ( in_array( strtolower( substr( $pattern, 1 ) ), self::$varTypes ) )
        ){
            switch( substr( $pattern, 1 ) ){
                
                case "null":
                    return ( substr( $pattern, 0, 1 ) == ":" ) == is_null( $val );

                case "num":
                case "number":
                case "numeric":
                    return ( substr( $pattern, 0, 1 ) == ":" ) == is_numeric( $val );

                case "int":
                case "integer":
                    return ( substr( $pattern, 0, 1 ) == ":" ) == is_int( $val );

                case "float":
                    return ( substr( $pattern, 0, 1 ) == ":" ) == is_float( $val );

                case "bool":
                case "boolean":
                    return ( substr( $pattern, 0, 1 ) == ":" ) == is_bool( $val );

                case "string":
                    return ( substr( $pattern, 0, 1 ) == ":" ) == is_string( $val );

                case "array":
                    return ( substr( $pattern, 0, 1 ) == ":" ) == is_array( $val );

                case "object":
                    return ( substr( $pattern, 0, 1 ) == ":" ) == is_object( $val );

            }
        }
        
        return $val == $pattern;
    }
}