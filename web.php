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
require_once LIB . '/web/session.php';
require_once LIB . '/web/mime.php';



class Web extends Engine
{
    /* Session */
    private Session | null  $session    = null;
    /* Current url */
    private Url | null      $url        = null;
    /* Current content */
    private array           $context    = [];
    /* URI path */
    private array           $path       = [];



    /*
        Create Web Appllication object
    */
    static public function create()
    :self
    {
        $result = new Web();
        $result -> session = Session::create( $result );
        return $result;
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
            $_SERVER[ 'REMOTE_ADDR' ] .
            '_' .
            md5( $_SERVER[ 'HTTP_USER_AGENT' ]);

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
        if
        (
            empty( $this -> url -> getPath()) &&
            empty( $this -> url -> getParams())
        )
        {
            /* Set default path */
            $this -> setDefaultUrl();
        }

        /* Read default context from config */
        $this -> setContext
        (
            $this -> getParam([ 'web', 'default', 'context' ], [] )
        );

        /* Open session */
        $this -> session -> open();

        /* Reset file name */
        $contentFileName = null;

        /*
            Run payload
        */

        /* Buffers on. Preven all output for client */
        ob_start();

        /* Create and run web payload */
        $payload = Payload::create
        (
            $this,
            implode( '/', $this -> url -> getPath() )
        );

        /* Return buffer output and clear it */
        $rawOutput = ob_get_clean();

        /* Check raw empty */
        if( strlen( $rawOutput ) > 0 )
        {
            /* For non empty buffer ... */
            $content = $rawOutput;
            $contentType = Mime::TXT;
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
                : Mime::HTML;

                /* Get content type from payload*/
                $contentFileName
                = method_exists( $payload, 'getContentFileName' )
                ? $payload -> getContentFileName()
                : null;
            }
            else
            {
                /* Return error */
                $content = $payload -> getResultAsArray();
                $contentType = Mime::JSON;
            }

            switch( $contentType )
            {
                case Mime::JSON:
                    $content = json_encode
                    (
                        $content,
                        JSON_UNESCAPED_SLASHES |
                        JSON_UNESCAPED_UNICODE |
                        JSON_PRETTY_PRINT
                    );
                break;
                case Mime::YAML:
                    $content = yaml_emit( $content );
                    if( empty( $contentFileName ))
                    {
                        $contentFileName = 'file.yaml';
                    }
                break;
            }
        }

        /* Send session */
        $this -> session -> send();

        /* Set content type header */
        header( 'Content-Type: ' . $contentType );

        /* Set content disposition */
        if( !empty( $contentFileName ))
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
            $this -> getLog() -> line( $contentType ) -> headerHide();
            print_r( $content . PHP_EOL );
            $this -> getLog() -> headerRestore() -> line();
        }
        else
        {
            print_r( $content );
        }

        return $this;
    }




    /**************************************************************************
        File path utils
    */



    /**************************************************************************
        Utils
    */

    public function setDefaultUrl()
    {
        $this -> url -> setUri
        (
            $this -> getParam([ 'web', 'default', 'uri' ], '' )
        );
        return $this;
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
    :array
    {
        return $this -> context;
    }


    /*
        Return application session
    */
    public function getSession()
    :Session
    {
        return $this -> session;
    }



    /*
        Return application main url object
    */
    public function getUrl()
    :Url
    {
        return $this -> url;
    }


}
