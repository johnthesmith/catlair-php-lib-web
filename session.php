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
    Session class.
    Contain all information about current session
*/



class TSession extends TResult
{
    const USER_UNKNOWN          = 'user_unknown';
    const SESSION_LOCAL_HOST    = 'localhost';

    private $SSLMethod          = null;
    private $SSLKey             = null;
    private $SSLLengthVector    = null;

    /* Private declration */
    private $JSON               = null;

    /*
        Constructor
    */
    public function __construct
    (
        $ASSLMethod         = 'aes-256-cbc',
        $ASSLKey            = null,
        $ASSLLengthVector   = 32
    )
    {
        /* Define empy JSON session */
        $this -> JSON = [];

        $this
        -> SetDomain            ( null )
        -> SetSite              ( null )
        -> SetLanguage          ( null )
        -> SetSSLMethod         ( $ASSLMethod )
        -> SetSSLKey            ( $ASSLKey )
        -> SetSSLLengthVector   ( $ASSLLengthVector )
        -> Set
        (
            'Remote',
            $this -> IsCLI()
            ? self::SESSION_LOCAL_HOST
            : $_SERVER[ 'REMOTE_ADDR' ]
        );
    }



    /*
        Static create method.
        Recomended for use.
    */
    static public function Create
    (
        $ASSLMethod         = 'aes-256-cbc',
        $ASSLKey            = null,
        $ASSLLengthVector   = 16
    )
    {
        return new TSession( $ASSLMethod, $ASSLKey, $ASSLLengthVector );
    }



    public function IsCLI()
    {
        return php_sapi_name() == 'cli';
    }



    /*
        Open new Session
    */
    public function &Open( $ASession = null )
    {
        if( $this -> IsCLI())
        {
            /* Session ID form input */
            $this
            -> SetDomain    ( DOMAIN_LOCALHOST )
            -> SetSite      ( null )
            -> SetLanguage  ( null );
        }
        else
        {
            /* Получение сессии из куки */
            $this -> SetSessionInfo( $ASession );
            /* Check the session on expire */
            if( $this -> GetExpireAfter() < 0 ) $this -> Clear();
        }

        return $this;
    }



    /*
        Set session data in to the cookie
    */
    public function &Send()
    {
        /* return session ID to cookies */
        if ( !self::IsCLI() )
        {
            setcookie( 'session', $this -> GetSessionInfo(), 0, '/', '', true );
        }
        return $this;
    }




    /*
        Create new session ID
    */
    public function &Clear()
    {
        $this -> Del( 'Login' );      /* Удаляем из текущей сессии логин */
        $this -> Del( 'LoginSite' );  /* Удаляем из текущей сессии сайт логина */
        $this -> Del( 'Token' );      /* Удаляем из текущей сессии токен */
        return $this;
    }



    /*
        Return encripted session information
    */
    public function GetSessionInfo
    (
        int $AExpire = null
    )
    {
        $InitVector = openssl_random_pseudo_bytes( $this -> GetSSLLengthVector() );

        /* Copy of array */
        $JSON = array_merge( $this -> JSON );

        /* */
        if( !empty( $AExpire ) )
        {
            $JSON[ 'Expire' ] = TMoment :: Create() -> Now() -> Add( $AExpire ) -> Get();
        }

        $SessionInfo = bin2hex( $InitVector ) . ' ' . bin2hex
        (
            openssl_encrypt
            (
                json_encode( $JSON ),
                $this -> GetSSLMethod(),
                $this -> GetSSLKey(),
                OPENSSL_RAW_DATA,
                $InitVector
            )
        );

        return (string) $SessionInfo;
    }



    /*
        Fill session object from encripted session information
    */
    private function &SetSessionInfo
    (
        $ASessionText
    )
    {
        $SessionPart = explode( ' ', $ASessionText );

        if ( count( $SessionPart) > 1 )
        {
            $SessionText = openssl_decrypt
            (
                hex2bin( $SessionPart[ 1 ]),
                $this -> GetSSLMethod(),
                $this -> GetSSLKey(),
                OPENSSL_RAW_DATA,
                hex2bin( $SessionPart[ 0 ])
            );
            $JSON = json_decode( $SessionText, true );
        }
        else
        {
            $JSON = null;
        }

        if( ! empty( $JSON ))
        {
            $this -> JSON = $JSON;
        }
        else
        {
            $this -> Clear();
        }

        return $this;
    }



    /*
        Read session parameter form $AKey
     */
    public function Get
    (
        string  $AKey,
        $ADefault = null
    )
    {
        if
        (
            array_key_exists( $AKey, $this -> JSON ) &&
            ! empty( $this -> JSON[ $AKey ] )
        )
        {
            $Result = (string) $this -> JSON[ $AKey ];
        }
        else
        {
            $Result = $ADefault;
        }
        return $Result;
    }



    /*
        Write session parameter
    */
    public function Set
    (
        $AKey = null,   /* Key name */
        $AValue = null  /* Value */
    )
    {
        if( !empty( $AKey ))
        {
            $this -> JSON[ $AKey ] = $AValue;
        }
        return $this;
    }



    /*
        Remove key by name
    */
    public function Del
    (
        $AKey
    )
    {
        if ( array_key_exists( $AKey, $this -> JSON ))
        {
            unset( $this -> JSON[ $AKey ]);
        }
        return $this;
    }



    /*
        Work with named params
    */
    private function SetID($AID)
    {
        return $this -> Set( 'ID', $AID );
    }



    /*
        Return the current session ID
    */
    public function GetID()
    {
        return $this -> Get( 'ID' );
    }



    /*
        Set site value
    */
    public function SetExpireAfter
    (
        int $AValue /* Expare in microsecond from current moment */
    )
    {
        return $this -> Set( 'Expire', TMoment :: Create() -> Now() -> Add( $AValue ) -> Get() );
    }



    /*
        Set site value
    */
    public function GetExpireAfter()
    {
        return ((float)$this -> Get( 'Expire' )) - TMoment :: Create() -> Now() -> Get();
    }



    /*
    */
    public function SetDomain( $ADomain )
    {
        return $this -> Set( 'Domain', $ADomain );
    }



    /*
    */
    public function GetDomain( $ADefault = null )
    {
        return $this -> Get( 'Domain', $ADefault );
    }



    /*
        Set languager for current session
    */
    public function SetLanguage( $AIDLang )
    {
        return $this -> Set( 'Language', $AIDLang );
    }



    /*
        Return the language for session
    */
    public function GetLanguage( $ADefault = null )
    {
        return $this -> Get( 'Language', $ADefault );
    }



    /*
        Set site value
    */
    public function SetSite( $ASite )
    {
        return $this -> Set( 'Site', $ASite );
    }



    /*
    */
    public function GetSite( $ADefault = null )
    {
        return $this -> Get( 'Site', $ADefault );
    }



    /*
        Set login for current ssession
        This function can not be call from project
        SECURE_SYSTEM
    */
    public function SetLogin( $ALogin )
    {
        return $this -> Set( 'Login', $ALogin );
    }



    /*
        Return current login
        SECURE_SYSTEM
     */
    public function GetLogin( $ADefault )
    {
        return $this -> Get( 'Login', $ADefault );
    }



    /*
    */
    public function GetSessionJSON()
    {
        return json_encode( $this -> JSON );
    }



    public function SetSSLMethod( $ASSLMethod )
    {
        $this -> SSLMethod = $ASSLMethod;
        return $this;
    }



    public function GetSSLMethod()
    {
        return $this -> SSLMethod;
    }



    public function SetSSLKey( $ASSLKey )
    {
        $this -> SSLKey = $ASSLKey;
        return $this;
    }



    public function GetSSLKey()
    {
        return $this -> SSLKey;
    }



    public function SetSSLLengthVector( $ASSLLengthVector )
    {
        $this -> SSLLengthVector = $ASSLLengthVector;
        return $this;
    }



    public function GetSSLLengthVector()
    {
        return $this -> SSLLengthVector;
    }



    public function GetJSON()
    {
        return $this -> JSON;
    }
}
