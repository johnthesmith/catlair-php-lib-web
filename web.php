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

    /*
        Defines a constant for the path name used to generate content
        dynamically using a builder from the specified file.
    */
    const MAKE_DIRECTIVE   = 'make';
    /*
        Defines a constant for the URL key used to invoke payloads.
    */
    const EXEC_DIRECTIVE   = 'exec';
    /*
        Defines a constant for the URL key used to return a static file
        without processing it through the templating engine.
    */
    const READ_DIRECTIVE  = 'read';

    /* Current key name used for returning static files */
    private $readDirective = self::READ_DIRECTIVE;
    /* Current key name used for dynamic content requests */
    private $makeDirective = self::MAKE_DIRECTIVE;
    /* Current key name used for payload execution */
    private $execDirective = self::EXEC_DIRECTIVE;

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
        /* Create url object */
        $this -> url = URL::create();

        /* Check the fpm mode */
        if( $this -> isFpm() )
        {
            $this -> url -> fromRequest();
        }
        else
        {
            /* Retrive url for cli */
            $this -> url -> parse( $this -> getParam([ 'web', 'cli', 'url' ]));
        }

        $method = null;
        $payload = null;
        $query = null;

        /* Retrive url path */
        $this -> path = $this -> url -> getPath();

        /* Retrive url mode */
        $mode = array_shift( $this -> path );


        switch( $mode )
        {
            case $this -> execDirective:
                /* Payload mode */
                $method = array_pop( $this -> path );
                $payload = implode( '/', $this -> path );
                $query = $this -> url -> getParams();
            break;
            case $this -> readDirective:
                /* Static file return mode */
                $payload = 'api';
                $method = 'read_content';
                $query = $this -> url -> getParams();
            break;
            case $this -> makeDirective:
                /* Dynamicc file return mode */
                $payload = 'api';
                $method = 'make_content';
                $query = $this -> url -> getParams();
            break;
        }

        /* Read default context from config */
        $this -> setContext( $this -> getParam([ 'web', 'default', 'context'], [] ));

        /* Define method and payload */
        $payload    ??= $this -> getParam( [ 'web', 'default', 'payload' ], 'api' );
        $method     ??= $this -> getParam( [ 'web', 'default', 'method' ], 'default_content' );
        $query      ??= $this -> getParam( [ 'web', 'default', 'query' ], [] );

        /* Buffers on. Preven all output for client */
        ob_start();

        /* Create and run web payload */
        $payload = Payload::create( $this, $payload )
        -> call( $method,  );

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
            $Result = 'Template ' . $AID . ' not found for site ' . $AIDSite . ' language ' . $AIDLanguage;
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



    public function getContext()
    {
        return $this -> context;
    }



    public function getPath()
    {
        return $this -> path;
    }
}
