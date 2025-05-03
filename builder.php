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

    Class for compiling content with embeded XML constructions:
    <cl param="command" param="value" ... />
    or
    <cl param="command" param="value" ... >
    <command param="value"/>
    </cl>

    https://github.com/johnthesmith/catlair-php-lib-web

    still@itserv.ru
*/



require_once LIB . '/core/log.php';             /* Include debug system */
require_once LIB . '/core/result.php';
require_once LIB . '/core/utils.php';           /* Include base utils for project */



class Builder extends Result
{
    /* Settings */
    private $FContent           = null;     /* Current content */
    private $FContentType       = null;     /* Content type like text/html text/css etc...*/
    private $FRecursDepth       = 100;      /* Maximum recursion depth */
    private $FRootPath          = '.';      /* Root path for content and libraries */

    /* Search and replace arrays */
    public $Income              = null;

    /* Internal objects */
    private $Optimize           = true;



    /*
        Catlairs Builder constructor
    */
    function __construct()
    {
        $this -> Income = new Params();
        $this -> SetOk();
    }



    static public function create()
    {
        return new Builder();
    }



    /*
        Remove all syntaxis from Content
        TODO it should be static
    */
    public function optimize( $AContent )
    {
        return preg_replace
        (
            [
                '/(?:("(?:.|\n)*?")|(?:( |^|\n)\/\/.*))/',
                '!/\*.*?\*/!s',
//                '/(?:\/\*(?:.|\n)*?\*\/)/',
                '/(?:("(?:.|\n)*?")|([\r\n ]+))/',
                '/(?:("(?:.|\n)*?")|(?: ?((?:>=)|(?:<=)|(?:===)|(?:==)|(?:!==)|(?:!=)|(?:[=\[\]{};:])) ?))/i',
                '/" /'
            ]
            ,
            [
                '$1$2',
                '',
                '$1 ',
                '$1$2',
                '"'
            ],
            $AContent
        );
    }



    /*
        Building content
    */
    public function build()
    {
        $this -> FContent = $this -> parsing( $this -> FContent );
        $this -> FContent = $this -> replace( $this -> FContent );
        if ( $this -> Optimize ) $this -> FContent = $this -> Optimize( $this -> FContent );
        return $this;
    }



    /*
        Building external content
        TODO it should be static
    */
    public function buildContent
    (
        string  $AContent,
        bool    $AOptimize  = null,
        bool    $AReplace   = true
    )
    {
        $AContent = $this -> parsing( $AContent );

        if( $AReplace )
        {
            $AContent = $this -> replace( $AContent );
        }

        if( $AOptimize === null ? $this -> Optimize : $AOptimize )
        {
            $AContent = $this -> Optimize( $AContent );
        }

        return $AContent;
    }



    /*
        Parsing any content from $AContent:string and return result
    */
    public function parsing( $AContent )
    {
        return $this -> pars( $AContent, 0 );
    }



    /*
        Return Income params interface
    */
    public function getIncome()
    {
        return $this -> Income;
    }



    /*
        Rename keys in array
    */
    public function incomeKeysConvert
    (
        array $AKeys = []
    )
    {
        $this -> Income -> RenameKeys( $AKeys );
        return $this;
    }



    /*
        `Replace all keys in the content $AContent:string and return it
    */
    public function replace( $AContent )
    {
        $Names = [];
        $Values = [];
        foreach( $this -> Income -> GetParams() as $Name => $Value )
        {
            switch( gettype( $Value  ))
            {
                case 'string':
                case 'bool':
                case 'double':
                {
                    array_push( $Names, '%' . $Name . '%' );
                    array_push( $Values, $Value );
                }
            }
        }
        return str_replace( $Names, $Values, $AContent );
    }



    /*
        Recurcive parsing for content from $ACountent:string with depth $ADepth:integer
        After parsing funciton will return new content
    */
    private function pars
    (
        string $AContent = '',
        int $ADepth
    )
    {
        $ADepth = $ADepth + 1;
        if( $ADepth < $this -> FRecursDepth )
        {
            do
            {
                    /* Getting list of tags over regvar with cl tag */
                    preg_match( '/\<cl(?:(\<)|".+?"|.|\n)*?(?(1)\/cl|\/)\>/', $AContent, $m, PREG_OFFSET_CAPTURE );
                    if( count( $m ) > 0 )
                    {
                        $b = $m[0][1];
                        $l = strlen ($m[0][0]);
                        $Source = $m[0][0];
                        if ( $l > 0 )
                        {
                            $Source = $this -> replace( $Source );
                            $XMLSource = '<?xml version="1.0"?>' . $Source;
                            libxml_use_internal_errors(true);
                            $XML = simplexml_load_string( $XMLSource );
                            if( empty( $XML ))
                            {
                                $this -> setResult
                                (
                                    'XMLError',
                                    [ 'Source' => $Source ],
                                    'XML Error [%Source%]'
                                );
                                $Result = '';
                            }
                            else
                            {
                                $Content = '';
                                $this -> BuildElement( $Content, $XML, $ADepth );
                                $Result = $Content;
                            }

                            /* Check recurstion error */
                            if ( $ADepth + 1 ==  $this -> FRecursDepth )
                            {
                                $this -> SetResult
                                (
                                    'Recurs',
                                    [],
                                    'Recursion depth limit [' . $ADepth .'] [' . $XMLSource . ']'
                                );
                            }
                            $AContent = trim( substr_replace( $AContent, $Result, $b, $l ));
                        }
                    }
            } while ( count( $m ) > 0 && $this -> IsOk() );
        }
        else
        {
            $AContent='';
        }

        return $AContent;
    }



    /*
        Recursive processing of XML command in cl tag
    */
    private function buildElement
    (
        string &$AContent,         /* contain text content */
        &$AElement,         /* simplexml it is text <cl param="value".../> or multiline XML <cl><command params="value"...></cl>. */
        $ARecursDepth       /* integer it is a current recursion depth. Zero by default. */
    )
    {
        if( $this -> IsOk() )
        {
            /* Processing of string as a key with parametes */
            $this -> Exec( $AContent, $AElement -> getName(), null, $AElement );

            /* Processing of directives in pair key=>value */
            if( $AElement -> getName() == 'cl' )
            {
                foreach( $AElement -> attributes() as $Key => $Value )
                {
                    $this -> Exec( $AContent, $Key, $Value, $AElement );
                }
            }
            /* Processing of internal cl tags */
            $AContent = $this -> Pars( $AContent, $ARecursDepth );
            /* Prpcessing of child stings with keys */
            foreach ($AElement -> children() as $Line => $Param)
            {
                $this -> BuildElement( $AContent, $Param, $ARecursDepth );
            }
        }
        return $this;
    }



    /*
        Extended work with cl tag
        It can be overriden in a child module
    */
    public function extExec
    (
        &$AContent,
        $ACommand,       /* Command */
        $AValue,
        $Params         /* List of named parameters */
    )
    {
        return false;
    }



    /*
        Main work with cl tag $AContent:string content
    */
    private function exec
    (
        &$AContent,
        $ACommand,       /* Command */
        $AValue,
        $Params
    )
    {
        /* Convert command to lower case */
        $ACommand = strtolower( $ACommand );

        /* Main command processing */
        switch( $ACommand )
        {
            default:
                if( $this -> IsOk())
                {
                    if( !$this -> extExec( $AContent, $ACommand, $AValue, $Params ))
                    {
                        if( $AValue )
                        {
                            /*
                                Replace parameters
                                Command will use like parameter name in content
                            */
                            $AContent = str_replace( '%'.$ACommand.'%', $AValue, $AContent );
                        }
                        else
                        {
                            /* Unknow key is found and it is not a macrochange string (%example%) in cl tag */
                            $this -> SetResult
                            (
                                'UnknownKey',
                                [],
                                'Unknown key [' . $ACommand . '] (cl; set; add; file; replace; convert; exec; header; include ets...)'
                            );
                        }
                    }
                }
            break;

            /* Empty tag. Nothing to do */
            case 'cl':
            break;

            /* Involuntary parsing for last content */
            /* <cl .... pars="true"/> */
            case 'pars':
                if( $this -> IsOk())
                {
                    if( $AValue ) $Value = $AValue;
                    else $Value = (string) $Params['value'];
                    if( $Value == 'true' ) $AContent = $this -> Pars( $AContent, 0 );
                }
            break;

            /* Set new content from $AValue:string */
            /* <cl content="IDContent"/> */
            case 'set':
                if( $this -> IsOk())
                {
                    if ($AValue) $AContent = $AValue;
                    else
                    {
                        if( $Params['value']) $AContent = $Params['value'];
                        else
                        {
                            $this -> SetResult
                            (
                                'ParamNotFound',
                                [],
                                'Parameter <b>value</b> not found'
                            );
                        }
                    }
                }
            break;

            /* Adding of content from $AValue:string */
            /* <cl ... add="IDContent"/> */
            case 'add':
                if( $this -> IsOk())
                {
                    if ($AValue) $Value = $AValue;
                    else $Value = (string) $Params[ 'value' ];
                    $AContent =  $AContent .= $Value;
                }
            break;

            /* This is an uncompleted URL builder. I must do it. */
            case 'url':
                if( $this -> IsOk())
                {
                    $Search = [];
                    $Replace = [];
                    /* параметры из */
                    foreach ($clURL as $Key => $Value)
                    {
                        array_push( $Search, '%' . $Key . '%' );
                        array_push( $Replace, $Value );
                    }
                    $AContent = str_replace( $Search, $Replace, $AContent );
                }
            break;

            /* Replace one parameter */
            case 'replace':
                if( $this -> IsOk())
                {
                    $AContent = str_replace( $Params[ 'from' ], $Params[ 'to' ], $AContent );
                }
            break;

            /* Mass replace parameters */
            case 'masreplace':
                if( $this -> IsOk())
                {
                    $Search = [];
                    $Replace = [];
                    foreach( $Params -> attributes() as $Key => $Value )
                    {
                        array_push( $Search, '%' . $Key . '%' );
                        array_push( $Replace, $Value );
                    }
                    $AContent = str_replace( $Search, $Replace, $AContent );
                }
            break;

            /*
                Replace named parameters from Template in the Content
            */
            case 'params':
                if( $this -> IsOk())
                {
                    $ID = $AValue ? $AValue : $Params[ 'id' ];
                    $Source = $this -> GetTemplate( (string) $ID );
                    $JSON = json_decode( $Source, true );
                    if( empty( $JSON ))
                    {
                        $this -> SetResult( 'JSONParsingParamsError', [ 'Source' => $Source ] );
                    }
                    else
                    {
                        $Search = [];
                        $Replace = [];
                        foreach( $JSON as $Key => $Value )
                        {
                            array_push( $Search, '%' . $Key . '%' );
                            array_push( $Replace, $Value );
                        }
                        $AContent = str_replace( $Search, $Replace, $AContent );
                    }
                }
            break;

            /* Collection */
            case 'keys':
                if( $this -> IsOk())
                {
                    $JSON = json_decode( $AContent, true );
                    if( $JSON !== null )
                    {
                        foreach( $JSON as $Key => $Value )
                        {
                            $this -> Income -> SetParam( $Key, $Value );
                        }
                        $AContent = '';
                    }
                    else
                    {
                        $this -> SetResult
                        (
                            'JSONParsingKeyError',
                            [
                                'Content' => $AContent,
                                'Command' => $ACommand,
                                'Value' => $AValue,
                                'Params' => $Params
                            ]
                        );
                    }
                }
            break;

            /* Optimize content */
            case 'optimize':
            case 'pure':
                if( $this -> IsOk())
                {
                    $AContent = $this -> Optimize( $AContent );
                }
            break;

            /* Optimize content */
            case 'content-type':
                if( $this -> IsOk())
                {
                    $this -> setContentType
                    (
                        strtolower( $AValue ? $AValue : $Params[ 'content-type' ])
                    );
                }
            break;

            /* Convert content to clear, html, pure, uri, md5, code */
            case 'convert':
                if( $this -> IsOk())
                {
                    $To = strtolower( $AValue ? $AValue : $Params[ 'to' ]);
                    $AContent = $this -> Replace( $AContent );
                    switch ($To)
                    {
                        case 'clear':   $AContent = ''; break;
                        case 'html':
                            $AContent = htmlspecialchars( $AContent, ENT_NOQUOTES );
                            $AContent = str_replace( PHP_EOL    , '<br>'    , $AContent );
                            $AContent = str_replace( '/*'       , '&#47;*'  , $AContent );
                            $AContent = str_replace( '*/'       , '*&#47;'  , $AContent );
                            $AContent = str_replace( ' '        , '&nbsp;'  , $AContent );
                        break;
                        case 'pure':
                            $AContent = preg_replace('/  +/','', preg_replace('/[\r\n]/',' ',$AContent));
                        break;
                        case 'uri':     $AContent = encodeURIComponent ($AContent); break;
                        case 'md5':     $AContent = md5( $AContent ); break;
                        case 'base64':  $AContent = base64_encode( $AContent ); break;
                        case 'default':; break;
                        default:
                            $this -> SetResult
                            (
                                'UnknownConvert', [],
                                'Unknown convert mode [' . $To . '] (clear; html; pure; uri; md5; base64; default)'
                            );
                        break;
                    }
                }
            break;


            /* Replace one parameter */
            case 'cover':
                if( $this -> IsOk())
                {
                    $AContent = '<div>' . implode( '</div><div>', explode( "\n", $AContent )). '</div>';
                }
            break;

            /* Get content from file or descript */
            case 'c':
            case 'content':
                if( $this -> IsOk())
                {
                    $ID = (string) ( $AValue ? $AValue : $Params[ 'id' ]);
                    $AContent = $this -> getTemplate( $ID );
                }
            break;

            /* Suppression of errors */
            case 'error':
                /* Collect params */
                if( $AValue ) $Value = $AValue;
                else $Value = $Params['value'];
                /* Work */
                if( strtolower( $Value ) == 'false')
                {
                    $this -> SetOk();
                }
            break;
        }

        return $this;
    }




    /*
        Return template
        or error if template not exists
    */
    public function getTemplate
    (
        string $AID,
        string $ADefault   = ''
    )
    :string
    {
        if( $this -> isOk() )
        {
            $this -> setResult
            (
                'TemplateNotFound',
                [
                    'ID'        => $AID,
                    'Default'   => $ADefault,
                ]
            );
        }
        return '';
    }



    /**************************************************************************
        Setters and getters
    */

    /*
        Set optimizer
    */
    public function setOptimize( $AValue )
    {
        $this -> Optimize = $AValue;
        return $this;
    }



    /*
        Set content
    */
    public function setContent( $AContent )
    {
        $this -> FContent = $AContent;
        return $this;
    }



    /*
        Get content
    */
    public function getContent()
    {
        return $this -> FContent;
    }



    /*
        Set depth of recursion from $ARecursDepth:integer
    */
    public function setRecursDepth($ARecursDepth)
    {
        $this->FRecursDepth = $ARecursDepth;
        return $this;
    }



    /*
        Get depth of recursion
    */
    public function getRecursDepth()
    {
        return $this->FRecursDepth;
    }



    /*
        Set Root path from $APath:string for file operations.
    */
    public function setRootPath($APath)
    {
        $this -> FRootPath = $APath;
        return $this;
    }



    /*
        Return root path for all file operations.
    */
    public function getRootPath()
    {
        return $this->FRootPath;
    }



    /*
        Set default language for builder
    */
    public function setIDLangDefault( $AValue )
    {
        $this -> IDLangDefault = $AValue;
        return $this;
    }



    /*
        Get default language for builder
    */
    public function getIDLangDefault()
    {
        return $this -> IDLangDefault;
    }



    public function setContentType
    (
        $AValue
    )
    {
        $this -> FContentType = $AValue;
        return $this;
    }



    public function getContentType()
    {
        return $this -> FContentType;
    }
}
