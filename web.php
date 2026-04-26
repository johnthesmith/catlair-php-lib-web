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
    /* Summon contract */
    private array | null    $summonContract     = [];


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
        Convert string to summon contract
    */
    static public function parseSummonContract
    (
        /* Raw data with summon contract */
        string $aString,
        /* Default type content */
        string $aDefaultTypeContent = 'text/plain'
    )
    :array | null
    {
        $result = json_decode( $aString, true );
        if( is_array( $result ) && isset( $result[ 'meta' ]['source' ]))
        {
            if( empty( $result[ 'content-type' ] ))
            {
                $result[ 'content-type' ] = $aDefaultTypeContent;
            }
        }
        else
        {
            $result = null;
        }
        return $result;
    }



    /*
        Web application on config event
        Append GET, POST, COOKIE params after base config
    */
    public function onConfig()
    :self
    {
        $this -> summonContract = self::parseSummonContract
        (
            file_get_contents( 'php://input' ),
            $_SERVER[ 'CONTENT_TYPE' ] ?? 'text/plain'
        );

        return $this
        -> appendParams( $_GET )
        -> appendParams( $_POST )
        -> appendParams( $_COOKIE )
        -> appendParams
        (
            $this -> summonContract !== null
            ? $this -> summonContract[ 'args' ] ?? []
            : []
        );
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
        /* Let inner headers */
        $this -> inHeaders = function_exists( 'getallheaders' )
        ? getallheaders()
        : [];

        /* First log event */
        $this -> getLog() -> dump( $this -> getInHeaders(), 'headers' );

        /* Let payload and method */
        $payloadName = null;
        $payloadMethod = null;

        /* Get IP rules */
        $rule = $this -> getRule();
        if( empty( $rule ))
        {
            $this -> setResult
            (
                'web-rule-not-found',
                [
                    'msg' => 'check web.rules config'
                ]
            );
        }
        else
        {
            if( !is_array( $rule ))
            {
                $this -> setResult
                (
                    'web-rule-is-not-array',
                    [
                        'msg' => 'check web.rules'
                    ]
                );
            }
            else
            {
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
                $this -> trace = $rule[ 'trace' ] ?? false;
            }
        }

        $this -> traceBegin
        (
            $_SERVER[ 'SERVER_ADDR' ] . ':' .
            $_SERVER[ 'SERVER_PORT' ] .
            $_SERVER[ 'REQUEST_URI' ]
        );

        /* Open session */
        if( $this -> getParam([ 'web', 'session', 'enabled' ], true ))
        {
            $this -> session = Session::create( $this );
            $this -> session -> open();
            $this -> setContext( (string) $this -> getSession() -> get( 'context' ));
        }

        /* Buffers on. Preven all output for client */
        ob_start();

        /* Read default context from config */
        $this -> setDefaultContexts
        (
            $this -> getParam([ 'web', 'default', 'contexts' ], [ 'default' ])
        );

        /* Check payload from url if empty */
        if( $payloadName === null )
        {
            $payloadName = $this -> url -> getPath()[ 0 ] ?? null;
            $payloadMethod = $this -> url -> getPath()[ 1 ] ?? null;
        }

        /* Create and call payload */
        $payload = WebPayload::create( $this, 'flow', 'internal' )
        -> resultFrom( $this )
        -> call( 'init' )
        -> resultTo( $this );

        /* Run payload */
        if( $payload -> isOk() && $payloadName !== null )
        {
            /* Mutate and run web payload */
            $payload = $payload
            -> mutate( $payloadName, 'api' )
            -> loadSummonState
            (
                file_get_contents( 'php://input' ),
            )
            -> run
            (
                $payloadMethod,
                is_array( $this -> url -> getParams())
        		? $this -> url -> getParams()
        		: []
            )
            ;
        }

        /* Postprocessing payload */
        $payload = $payload
        -> mutate( 'flow', 'internal' )
        -> call( 'postprocessing' )
        -> unmutate()
        ;

        /* Return buffer output and clear it */
        $rawOutput = ob_get_clean();

        /* Set error if payload is not web */
        if( !is_subclass_of( $payload, '\catlair\WebPayload' ))
        {
            $this -> setResult
            (
                'payload-is-not-web',
                [
                    'payload-class' => get_class( $payload )
                ]
            );
        }

        /* Check raw empty */
        if( strlen( $rawOutput ) > 0 )
        {
            /* For non empty buffer ... */
            $content = $rawOutput;
            $contentType = Mime::TXT;
        }
        else
        {
            if( $payload -> isOk())
            {
                /* Get content from payload */
                $content = $payload -> getContent();
                if( empty( $content ))
                {
                    $content = $payload -> getParams();
                }
                /* Get content type from payload*/
                $contentType = $payload -> getContentType();
                /* Get content type from payload*/
                $contentFileName = $payload -> getContentFileName();
            }
            else
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
                default:
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
        $this -> traceEnd
        (
            $_SERVER[ 'SERVER_ADDR' ] .
            ':' .
            $_SERVER[ 'SERVER_PORT' ] .
            $_SERVER[ 'REQUEST_URI' ] .
            '=' . $payload -> getCode()
        );

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
        "ip-masque-1, ... ":
            "*|port-1, ..."
                "*|host-1, ...":
                    "path*": { rules }
*/
    private function getRule()
    :array | null
    {
        /* Client ip */
        $ip = $_SERVER[ 'REMOTE_ADDR' ];
        /* Incoming port */
        $port = $_SERVER[ 'SERVER_PORT' ];
        /* Incoming host */
        $host = $this -> getUrl() -> getHost();
        /* Incoming path */
        $path = implode( '/', $this -> url -> getPath());
        $path = empty( $path ) ? "/" : $path;

        $this
        -> getLog()
        -> trace( 'Looking for the rule for' )
        -> param( 'ip', $ip )
        -> param( 'port', $port )
        -> param( 'host', $host )
        -> param( 'path', $path )
        -> lineEnd()
        ;

        /* Get rules from config */
        $rules = $this -> getParams()[ 'web' ][ 'rules' ] ?? [];
        /* Get current host */
        $host = $this-> getUrl() -> getHost();

        /* Loop for ip masque */
        foreach( $rules as $maskKey => $portsKeys )
        {
            if( is_array( $portsKeys ))
            {
                /* List of masks in key */
                $masks = array_map( 'trim', explode(',', $maskKey ));
                foreach( $masks as $mask )
                {
                    /* Check ip by range */
                    if( ip4Range( $ip, $mask ))
                    {
                        /* Loop for ports */
                        foreach( $portsKeys as $portsKey => $hostsKeys )
                        {
                            if( is_array( $hostsKeys ))
                            {
                                $ports = array_map( 'trim', explode( ',', $portsKey ));
                                if( in_array( $port, $ports ) || in_array( '*', $ports ))
                                {
                                    foreach( $hostsKeys as $hostsKey => $urisKeys )
                                    {
                                        if( is_array( $urisKeys ))
                                        {
                                            $hosts = array_map( 'trim', explode( ',', $hostsKey ));
                                            if( in_array( $host, $hosts ) || in_array( '*', $hosts ))
                                            {
                                                foreach( $urisKeys as $uriKey => $rule)
                                                {
                                                    if( fnmatch( $uriKey, $path ) )
                                                    {
                                                        /* Dump rule in to log */
                                                        $this
                                                        -> getLog()
                                                        -> dump( $rule, 'Result rule' )
                                                        -> lineEnd();
                                                        return is_array( $rule ) ? $rule : null;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return null;
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
        Set out headers key => value
    */
    public function setOutHeaders
    (
        array $a
    )
    :self
    {
        $this -> outHeaders = $a;
        return $this;
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
        $this -> trace( '>', $aLabel );
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
        $this -> trace( '<', $aLabel );
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
                . '|'
                . $aLabel
                . '|'
                . ( $now - $start )
            );
        }

        return $this;
    }

}
