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
    Реализует сулчаи:
        запуск апи
        возврат статического файла
        сборка динамического контента
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
    const DYNAMIC_KEY   = 'd';
    /*
        Defines a constant for the URL key used to invoke payloads.
    */
    const PAYLOAD_KEY   = 'p';
    /*
        Defines a constant for the URL key used to return a static file
        without processing it through the templating engine.
    */
    const STATIC_KEY    = 's';

    /* Current key name used for dynamic content requests */
    private $dynamicKey = self::DYNAMIC_KEY;
    /* Current key name used for payload execution */
    private $payloadKey = self::PAYLOAD_KEY;
    /* Current key name used for returning static files */
    private $staticKey  = self::STATIC_KEY;

    /* Current url */
    private $url = null;



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
        /* Create url incoming */
        $this -> url = URL::create() -> parse
        (
            (
                isset( $_SERVER[ 'HTTPS' ]) && $_SERVER[ 'HTTPS' ] === 'on'
                ? "https"
                : "http") .
            "://" .
            $_SERVER['HTTP_HOST'] .
            $_SERVER['REQUEST_URI']
        );

        /* Retrive url path */
        $path = $this -> url -> getPath();

        /* Retrive url mode */
        $mode = array_shift( $path );

        $method = null;
        $payload = null;

        switch( $mode )
        {
            case $this -> payloadKey:
                /* Payload mode */
                $method = array_pop( $path );
                $payload = implode( '/', $path );
            break;
            case $this -> staticKey:
                /* Static file return mode */
                $method = 'static';
                $payload = 'api';
            break;
            case $this -> dynamicKey:
                /* Dynamicc file return mode */
                $method = 'dynamic';
                $payload = 'api';
            break;
        }

        /* Define method and payload */
        $method ??= 'unknown';
        $payload ??= 'web';

        /* Buffers on. Preven all output for client */
        ob_start();

        /* Create and run web payload */
        $payload = Payload::create( $this, $payload ) -> call( $method );

        /* Return buffer output and clear it */
        $rawOutput = ob_get_clean();

        if( strlen( $rawOutput ) > 0 )
        {
            /* for non empty buffer */
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
                = method_exists( $payload,  'getContent' )
                ? $payload -> getContent()
                : null;

                /* Get content type from payload*/
                $contentType
                = method_exists( $payload,  'getContentType' )
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
                'Content-Disposition: attachment; filename="' .
                $contentFileName .
                '"'
            );
        }

        /* Final out put */
        print_r( $content );

        return $this;
    }
}

