<?php
/*
    Catlair PHP Copyright (C) 2021 https://itserv.ru

    This program (or part of program) is free software: you can redistribute it
    and/or modify it under the terms of the GNU Aferro General Public License as
    published by the Free Software Foundation, either version 3 of the License,
    or (at your option) any later version.

    This program (or part of program) is distributed in the hope that it will be
    useful, but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Aferro
    General Public License for more details. You should have received a copy of
    the GNU Aferror General Public License along with this program. If not, see
    <https://www.gnu.org/licenses/>.
*/



namespace catlair;



/*
    Web server engine. Fork of Pusa.
    https://gitlab.com/catlair/pusa/-/tree/main
    Inherits from Engine. Extends the application with web server functionality.

    https://github.com/johnthesmith/catlair-php-lib-web
*/

/*
    Implements:
        execution of an arbitrary payload,
        returning a static file,
        building and returning dynamic content
*/



/* Core libareies */
require_once LIB . '/core/url.php';
require_once LIB . '/core/parse.php';

/* Application libraries */
require_once LIB . '/app/engine.php';
require_once LIB . '/app/payload.php';



class Web extends Engine
{
    /*
        Baisic content type
    */
    const HTML  = 'text/html';
    const PLAIN = 'text/plain';
    const JSON  = 'application/json';
    const XML   = 'application/xml';
    const CSS   = 'text/css';
    const JS    = 'application/javascript';
    const YAML  = 'application/x-yaml';
    const SVG   = 'image/svg+xml';
    const PNG   = 'image/png';
    const JPG   = 'image/jpeg';
    const GIF   = 'image/gif';
    const ICO   = 'image/x-icon';



    /*
        Main command directive names
    */



    /* Current url */
    private $url = null;
    /* Current content */
    private array $context = [];
    /**/
    private array $path = [];



    /*
        Create Web Appllication object
    */
    static public function create()
    :self
    {
        return new Web();
    }



    /**************************************************************************
        Events
    */

    /*
        on log settings event
    */
    public function onLogSetting()
    :self
    {
        /* Call parent */
        parent::onLogSetting();

        /* Create url object */
        $this -> url = URL::create();

        /* Check the fpm mode */
        if( $this -> isFpm() )
        {
            /* Build URL from request */
            $this -> url -> fromRequest();

            $logsPathPrefix =
            $_SERVER['REMOTE_ADDR'] .
            '_' .
            md5( $_SERVER['HTTP_USER_AGENT'] );

            /* Switch log to file */
            $this
            -> getLog()
            -> setDestination( Log::FILE )
            -> setLogPath( $this -> getLogsPath( $logsPathPrefix ) )
            -> setLogFile
            (
                preg_replace
                (
                    '/[^a-zA-Z0-9]+/',
                    '_',
                    $this -> url -> toString()
                )
            ) -> warning('asd') -> lineEnd();
            ;
        }
        else
        {
            /* Retrive url for cli */
            $this -> url -> parse
            (
                $this -> getParam([ 'web', 'cli', 'url' ])
            );
        }

        return $this;
    }



    /*
        Web application main method.
        Accept:
            1. call api on payloads
                protocol://domain/$payloadKey/path/payload/method?arguments...
                call payload method with arguments.
            2. return static file
                protocol://domain.zone/$staticKey/path/filename.ext?arguments...
                read and return file from ./rw/content/
            3. return dynamyc file
                protocol://domain.zone/$dynamicKey/path/filename.ext?arguments...
                read and return file from ./rw/content/
    */
    public function onRun()
    :self
    {
        /*
            Read main config arguments
        */

        /* Read default context from config */
        $this -> setContext
        (
            $this -> getParam([ 'web', 'default', 'context'], [] )
        );

        /*
            Build route
        */

        /* Retrive url path */
        $this -> path = $this -> url -> getPath();

        /*
            Run payload
        */

        /* Buffers on. Preven all output for client */
        ob_start();

        /* Create and run web payload */
        $payload = Payload::create( $this, implode( '.', $this -> path ) )
//        -> call( $route[ 'method' ], $route[ 'query' ])
        ;
exit(1);
        /* Return buffer output and clear it */
        $rawOutput = ob_get_clean();

        /* Check raw empty */
        if( strlen( $rawOutput ) > 0 )
        {
            /* For non empty buffer ... */
            $content = $rawOutput;
            $contentType = 'text/plain; charset=utf-8';
            $contentFileName = null;
        }
        else
        {
            if( $payload -> isOk() )
            {
                /* Get content from payload */
                $content
                = method_exists( $payload, 'getContent' )
                ? $payload -> getContent()
                : null;

                /* Get content type from payload*/
                $contentType
                = method_exists( $payload, 'getContentType' )
                ? $payload -> getContentType()
                : self::HTML;

                $contentFileName = null;
            }
            else
            {
                /* Return error */
                $content = $payload -> getResultAsArray();
                $contentType = self::JSON;
                $contentFileName = null;
            }

            switch( $contentType )
            {
                case self::JSON:
                    $content = json_encode
                    (
                        $content,
                        JSON_UNESCAPED_SLASHES |
                        JSON_UNESCAPED_UNICODE |
                        JSON_PRETTY_PRINT
                    );
                break;
                case self::YAML:
                    $content = yaml_emit( $content );
                    $contentFileName = 'content.yaml';
                break;
            }
        }

        /* Set content type header */
        header( 'Content-Type: ' . $contentType );

        /* Set content disposition */
        if( !empty( $contentDisposition ))
        {
            header
            (
                'Content-Disposition: attachment; filename=' .
                '"' .
                $contentFileName .
                '"'
            );
        }

        /* Final out put */
        if( $this -> isCli() )
        {
            $this -> getLog() -> prn( $content );
        }
        else
        {
            print_r( $content );
        }

        return $this;
    }



    /**************************************************************************
        Utils
    */

    /*
        Return template content
    */
    public function getTemplate
    (
        string $aId         = null,
        array  $aContext    = []
    )
    {
        $file = $this -> getContentFileAny( $AID, $AIDSite, $AIDLanguage );

        if( !empty( $File ))
        {
            $Result = @file_get_contents( $File );
        }
        else
        {
            $Result = 'Template '
            . $AID
            . ' not found for site '
            . $AIDSite
            . ' language '
            . $AIDLanguage;
        }
        return $Result;
    }





    /**************************************************************************
        Setters and getters
    */
    public function setContext
    (
        array $a
    )
    {
        sort( $a );
        $this -> context = $a;
        return $this;
    }


    /*
        Set curernt contex
    */
    public function getContext()
    {
        return $this -> context;
    }



    /*
        Return current url path
    */
    public function getPath()
    {
        return $this -> path;
    }



    /*
        Return payload route array
    */
    public function getRoute
    (
        /* Route name */
        string $aPayloadName
    )
    /* Route array */
    :array
    {
        $full = explode( '.', $aPayloadName );
        $first = $full ? $full[ 0 ] : null;
        if( $first !== null && $this -> existsRoute( $first ))
        {
            $result = parent::getRoute( $first );
        }
        else
        {
            $result = parent::getRoute( $aPayloadName );
        }
        return $result;
    }

}













//
///*
//    Return payload route array
//*/
//public function getRoute
//(
//    /* Route name */
//    array  $aPath,
//    /* Optional specific project */
//    string $aProject    = null,
//)
///* Route array */
//:array
//{
//    $result = [];
//
//    /* Extract head element of path */
//    $head = $aPath[0] ?? null;
//    $file = $this -> getRouteFileAny( $head . '.yaml' );
//    if( $file === false )
//    {
//        $path = implode( '.', $aPath );
//        $file = $this -> getRouteFileAny( $path . '.yaml' );
//    }
//
//    if( $file !== false )
//    {
//        $result = clParse( @file_get_contents( $file ), 'yaml', $this );
//    }
//    else
//    {
//        $content = '';
//        $this
//        -> getLog()
//        -> trace( 'Route not found' )
//        -> param( 'path', $path )
//        -> lineEnd();
//    }
//
//    return ( $result[ 'enabled' ] ?? true ) ? $result : [];
//}
