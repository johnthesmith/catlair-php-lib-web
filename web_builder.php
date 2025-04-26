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
    Content Builder for the web.php engine.
*/



require_once LIB . '/web/builder.php';  /* Include debug system */
require_once LIB . '/web/web.php';      /* Include debug system */



class WebBuilder extends Builder
{
    private $Web = null;



    /*
        Create web builder
    */
    static public function create()
    {
        $Result = new WebBuilder();
        return $Result;
    }



    /*
        Building external content
    */
    static public function createContent
    (
        string  $AContent   = null,
        bool    $AOptimize  = null,
        bool    $AReplace   = true,
        Web     $AWeb       = null
    )
    {
        $AContent = $AContent === null ? '' : $AContent;

        $Builder = WebBuilder::create();
        $Builder -> Web = $AWeb;
        $Builder -> setContentType( $AWeb -> getContentType() );

        /*
            Build the income peremeters
            This %keys% will be replaced in reuslt content to value of keys.
        */
        $Builder -> Income
        -> setParams
        (
            array_merge
            (
                $_COOKIE,
                $_GET,
                $_POST
            )
        );

        $Content = $Builder -> parsing( $AContent );

        if( $AReplace )
        {
            $Content = $Builder -> replace( $Content );
        }

        if( $AOptimize )
        {
            $Content = $Builder -> optimize( $Content );
        }

        $AWeb -> setContentType( $Builder -> getContentType() );

        return $Content;
    }



    /*
        Extended work with cl tag
    */
    public function extExec
    (
        &$AContent,
        $ACommand,       /* Command */
        $AValue,
        $Params         /* List of named parameters */
    )
    {
        switch( $ACommand )
        {

            /* Write header */
            case 'header':
                if( $this -> IsOk())
                {
                    $Header = strtolower( $AValue ? $AValue : $Params[ 'header' ]);
                    header( $AContent, true );
                }
            break;

            /* Call payload */
            case 'payload':
            {
                if( $this -> IsOk())
                {
                    $Payload = (string) ( $AValue ? $AValue : $Params[ 'payload' ]);
                    $Method = (string) $Params[ 'method' ];
                    if( !empty( $Payload ))
                    {
                        if( !empty( $Method ))
                        {
                            /* Создание полезной нагрузки */
                            $payload = WebPayload::create( $this -> Web, $Payload );
                            $payload -> run( $Method, ((array) $Params )[ '@attributes' ] );
                            $payload -> resultTo( $this );
                            if( $payload -> isOk() )
                            {
                                $AContent = $payload -> getContent();
                            }
                        }
                        else
                        {
                            $this -> setResult( 'MethodIsEmpty', [ 'PayloadName' => $PayloadName, 'Method' => $Method ] ) -> resultWarning();
                        }
                    }
                }
            }
        }
        return true;
    }



    /*
        Return template over Web
        It overrids the Builder method
    */
    public function getTemplate
    (
        string $AID,
        string $ADefault   = null
    )
    :string
    {
        return $this -> Web -> getTemplate( $AID );
    }
}
