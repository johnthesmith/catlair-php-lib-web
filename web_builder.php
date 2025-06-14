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
    https://github.com/johnthesmith/catlair-php-lib-web
*/



require_once LIB . '/web/builder.php';  /* Include debug system */
require_once LIB . '/web/web.php';      /* Include debug system */



class WebBuilder extends Builder
{
    /*
        Create web builder
    */
    static public function create
    (
        $aWeb
    )
    {
        $Result = new WebBuilder( $aWeb );
        return $Result;
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
                    $header = strtolower( $AValue ? $AValue : $Params[ 'header' ]);
                    header( $header, true );
                }
            break;

            /* Call payload */
            case 'payload':
            {
                if( $this -> isOk())
                {
                    $payload = (string) ( $AValue ? $AValue : ( $Params[ 'payload' ] ?? '' ));
                    $method = (string) $Params[ 'method' ] ?? '';

                    /* Создание полезной нагрузки */
                    $payload = WebPayload::create( $this -> getApp(), $payload );
                    if( $payload -> isOk() )
                    {
                        $payload -> run( $method, ((array) $Params )[ '@attributes' ] );
                        if( $payload -> isOk() )
                        {
                            $AContent = $payload -> getContent();
                        }
                    }

                    $payload -> resultTo( $this );
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
        string $ADefault = null,
        array $aContext  = []
    )
    :string
    {
        /* Call web pallication */
        return $this -> getOwner() -> getTemplate( $AID, $aContext );
    }



    public function getApp()
    {
        return $this -> getOwner() -> getApp();
    }
}
