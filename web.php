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
require_once LIB . '/core/ip_utils.php';

/* Application libraries */
require_once LIB . '/app/engine.php';
require_once LIB . '/web/web_payload.php';
require_once LIB . '/web/session.php';
require_once LIB . '/web/mime.php';



class Web extends Engine
{
    /* Session */
    private Session | null  $session            = null;
    /* Current url */
    private Url | null      $url                = null;
    /* Current context */
    private string          $context            = '';
    /* Default failback contexts list */
    private array           $defaultContexts    = [ 'default' ];
    /* URI path */
    private array           $path               = [];
    /* Incoming http header */
    private array           $inHeaders          = [];
    /* Outcoming http header */
    private array           $outHeaders         = [];
    /* Trace enabled */
    private bool            $trace              = false;


    /*
        Create Web Appllication object
    */
    static public function create()
    :self
    {
        $result = new Web();
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
            md5( $_SERVER[ 'HTTP_USER_AGENT' ] ?? 'unknown' );

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
            );
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
        Web application on config event
        Append GET, POST, COOKIE params after base config
    */
    public function onConfig()
    :self
    {
        /* Read JSON from body request */
        $input = file_get_contents( 'php://input' );
        $jsonData = json_decode( $input, true );

        return $this
        -> appendParams( $_GET )
        -> appendParams( $_POST )
        -> appendParams( $_COOKIE )
        -> appendParams( $jsonData ?? [] );
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
        /* First log event */
        $this -> getLog() -> dump( $this -> getInHeaders(), 'headers' );

        /* Let inner headers */
        $this -> inHeaders = getallheaders();

        /* Let payload and method */
        $payloadName = null;
        $payloadMethod = null;

        /* Get IP rules */
        $rule = $this -> getRule( $_SERVER[ 'REMOTE_ADDR' ] ?? '' );
        if( is_array( $rule ))
        {
            /* Dump rule in to log */
            $this -> getLog() -> dump( $rule, 'rule' );

            /* Apply rule uri */
            $ruleUri = $rule[ 'uri' ] ?? null;
            if( $ruleUri !== null )
            {
                $this -> url -> setUri( $ruleUri );
            }

            /* Apply rule action */
            $ruleAction = $rule[ 'action' ] ?? null;
            if( $ruleAction !== null )
            {
                $parts = explode( '/', $ruleAction );
                $payloadName = $parts[ 0 ] ?? null;
                $payloadMethod  = $parts[ 1 ] ?? null;
            }

            /* Apply header trace from rule */
            $this -> trace = $rule[ 'header' ][ 'trace' ] ?? false;
        }

        $this -> traceBegin( $_SERVER[ 'SERVER_ADDR' ]);

        /* Open session */
        if( $this -> getParam([ 'web', 'session', 'enabled' ], true ))
        {
            $this -> session = Session::create( $this );
            $this -> session -> open();
            $this -> setContext( (string) $this -> getSession() -> get( 'context' ));
        }

        /* Read default context from config */
        $this -> setDefaultContexts
        (
            $this -> getParam([ 'web', 'default', 'contexts' ], [ 'default' ])
        );

        /* Buffers on. Preven all output for client */
        ob_start();

        /* Check paylaod from url if empty */
        if( $payloadName === null )
        {
            $payloadName = $this -> url -> getPath()[ 0 ] ?? null;
            $payloadMethod = $this -> url -> getPath()[ 1 ] ?? null;
        }

        /* Create and call payload */
        $payload = WebPayload::create( $this, 'flow', 'internal' )
        -> call( 'init' )
        -> resultTo( $this );

        /* Run payload */
        if( $payload -> isOk() && $payloadName !== null )
        {
            $this -> traceBegin( $payloadMethod );
            /* Mutate and run web payload */
            $payload = $payload
            -> mutate( $payloadName, 'api' )
            -> run
            (
                $payloadMethod,
                is_array( $this -> url -> getParams())
        		? $this -> url -> getParams()
        		: []
            )
            ;
            $this -> traceEnd( $payloadMethod );
        }

        /* Postprocessing payload */
        $payload = $payload
        -> mutate( 'flow', 'internal' )
        -> call( 'postprocessing' )
        -> unmutate()
        ;

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
            if
            (
                $payload -> isOk()
                && ( is_subclass_of( $payload, '\catlair\WebPayload' ))
            )
            {
                /* Get content from payload */
                $content = $payload -> getContent();
                /* Get content type from payload*/
                $contentType = $payload -> getContentType();
                /* Get content type from payload*/
                $contentFileName = $payload -> getContentFileName();
            }
            else
            {
                $this -> setResult( 'payload-is-not-web' );
            }

            if( !$payload -> isOk() )
            {
                /* Return error */
                $content = $payload -> getResultHistory();
                $contentType = Mime::JSON;
            }
        }

        if( is_array( $content ))
        {
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
        if( $this -> session != null )
        {
            $this -> session -> send();
        }

        /* Set content type header */
        $this -> setOutHeader( 'Content-Type', $contentType );

        /* Set content disposition */
        if( !empty( $contentFileName ))
        {
            $payload -> setOutHeader
            (
                'Content-Disposition',
                'attachment; filename=' .
                '"' .
                $contentFileName .
                '"'
            );
        }

        /* Final trace */
        $this -> traceEnd( $_SERVER[ 'SERVER_ADDR' ]);

        /* Move X-headers in to out headers */
        foreach( $this -> inHeaders as $name => $value )
        {
            if (strpos($name, 'X-') === 0)
            {
                $this -> outHeaders[ $name ] = $value;
            }
        }

        /* Apply accumulated headers */
        foreach( $this -> getOutHeaders() as $key => $value )
        {
            if( !empty( $value ))
            {
                header( $key . ': ' . (string)$value );
            }
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
        Utils
    */


    /*
        Return eulw from config web.rules
        {
            "ip-masque, ... ":
            {
                "hostname":
                {
                    "path-masque": payload/method
                    ...
                },
                ...
            },
            ...
        }
    */
    private function getRule
    (
        /* Current ip */
        string $aIp
    )
    :array | null
    {
        $result = null;
        $rules = $this -> getParams()[ 'web' ][ 'rules' ] ?? [];
        /* Loop for ip masque */
        foreach( $rules as $masque => $item )
        {
            $masks = explode(',', $masque);
            foreach( $masks as $mask )
            {
                /* Check ip by range */
                if( ip4Range( $aIp, trim( $mask )))
                {
                    if( is_array( $item ))
                    {
                        /* Get current host */
                        $host = $this-> getUrl() -> getHost();
                        $hostRule = $item[ $host ] ?? $item[ '*' ] ?? null;
                        if( is_array( $hostRule ))
                        {
                            /* Get current path */
                            $path = implode( '/', $this -> url -> getPath());
                            $path = empty( $path ) ? "/" : $path;
                            foreach( $hostRule as $pattern => $rule )
                            {
                                if( fnmatch( $pattern, $path ))
                                {
                                    $result = is_array( $rule ) ? $rule : [];
                                    break 3;
                                }
                            }
                        }
                    }
                }
            }
        }
        return $result;
    }



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
        string $a
    )
    :self
    {
        $this -> context = $a;
        return $this;
    }



    /*
        Set curernt contex
    */
    public function getContext()
    :string
    {
        return $this -> context;
    }



    /*
        Set default contexts list
    */
    public function setDefaultContexts
    (
        array $a
    )
    {
        $this -> defaultContexts = $a;
        return $this;
    }



    /*
        Return default contexs list
    */
    public function getDefaultContexts()
    :array
    {
        return $this -> defaultContexts;
    }



    /*
        Return application session
    */
    public function getSession()
    :Session | null
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



    /**************************************************************************
        Headers
    */


    /*
        Return income headers key => value
    */
    public function getInHeaders()
    :array
    {
        return $this -> inHeaders;
    }



    /*
        Return incom http header value by key
    */
    public function getInHeader
    (
        string $aKey,
        string $aDefault = ''
    )
    :string
    {
        return $this -> inHeaders[ $aKey ] ?? $aDefault;
    }



    /*
        Return incom http header value by key
    */
    public function setInHeader
    (
        string $aKey,
        string | null $aValue
    )
    :self
    {
        $this -> inHeaders[ $aKey ] = $aValue;
        return $this;
    }



    /*
        Return income headers key => value
    */
    public function getOutHeaders()
    :array
    {
        return $this -> outHeaders;
    }



    /*
        Return income http header value by key
    */
    public function getOutHeader
    (
        string $aKey,
        string $aDefault = ''
    )
    :string
    {
        return $this -> outHeaders[ $aKey ] ?? $aDefault;
    }



    /*
        Return income http header value by key
    */
    public function setOutHeader
    (
        string $aKey,
        string | null $aValue = ''
    )
    :self
    {

        $this -> outHeaders[ $aKey ] = $aValue;
        return $this;
    }



    /*
        Apply list of headers, split headers between
        inHeaders and outHeaders
    */
    public function applyHeaders
    (
        array $a
    )
    {
        /* Return headers */
        foreach( $a as $name => $value )
        {
            if( strpos( $name, 'X-' ) === 0 )
            {
                $this -> setInHeader( $name, $value );
            }
            else
            {
                $this -> setOutHeader( $name, $value );
            }
        }
        return $this;
    }



    /*
        Trace begin
    */
    public function traceBegin
    (
        string $aLabel
    )
    {
        $this -> trace( 'b', $aLabel );
        return $this;
    }



    /*
        Trace end
    */
    public function traceEnd
    (
        string $aLabel
    )
    {
        $this -> trace( 'e', $aLabel );
        return $this;
    }



    /*
        Trace to http header
    */
    public function trace
    (
        string $aEvent,
        string $aLabel
    )
    :self
    {
        if( $this -> trace )
        {
            /* Получаем текущее время */
            $now = ( int )(microtime(true) * 1000000 );

            /* Получаем время начала запроса */
            $start = ( int )$this -> getInHeader( 'X-Trace-Start' );
            if( empty( $start ))
            {
                $start = $now;
                $this -> setInHeader( 'X-Trace-Start', (string) $start );
            }

            /* Извлечение трассировки */
            $trace = $this -> getInHeader( 'X-Trace' );

            $this -> setInHeader
            (
                'X-Trace',
                ( empty( $trace ) ? '' : $trace . '; ' )
                . $aEvent
                . ':'
                . $aLabel
                . ':'
                . ( $now - $start )
            );
        }

        return $this;
    }

}
