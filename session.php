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



require_once LIB . '/web/web.php';


/*
    Session class.
    Contain information about current session
    https://github.com/johnthesmith/catlair-php-lib-web
*/
class Session extends Result
{
    const SESSION_LOCAL_HOST    = 'localhost';
    const SESSION_COOKIE        = 'session';
    const USER_GUEST            = 'guest';

    private Web | null  $app    = null;
    private $sslMethod          = '';
    private $sslKey             = '';
    private $sslLengthVector    = 16;
    /* Cookie exparation time in seconds */
    private $ttlSec    = 0;

    /*
        Private declration
        [
            i => unique id
            u => user
            t => exparation moment at mcs
            h => client host
            d => [ user data ]
        ]
    */
    private array $token = [];



    /*
        Constructor
    */
    public function __construct
    (
        Web             $aApp,
        string          $aSslMethod         = 'aes-256-cbc',
        string          $aSslKey            = '',
        int             $aSslLengthVector   = 16
    )
    {
        $this -> app = $aApp;
        /* Define ssl settings */
        $this
        -> setSslMethod         ( $aSslMethod )
        -> setSslKey            ( $aSslKey )
        -> setSslLengthVector   ( $aSslLengthVector )
        -> open()
        ;
    }



    /*
        Static create method.
        Recomended for use.
    */
    static public function create
    (
        Web     $aApp,
        string  $aSslMethod         = 'aes-256-cbc',
        string  $aSslKey            = '',
        int     $aSslLengthVector   = 16
    )
    :Session
    {
        return new Session
        (
            $aApp,
            $aSslMethod,
            $aSslKey,
            $aSslLengthVector
        );
    }



    /*
        Reset session
    */
    public function reset()
    :Session
    {
        /* Define empy token session */
        $this -> token =
        [
            'i' => clBase64ID(),
            'u' => self::USER_GUEST,
            't' => null,
            'h' => $this -> app -> isCLI()
            ? self::SESSION_LOCAL_HOST
            : $_SERVER[ 'REMOTE_ADDR' ],
            'd' => []
        ];
        return $this;
    }



    /*
        Open new Session
    */
    public function open()
    :Session
    {
        /* Retrive the token from the cookie */
        $source = $_COOKIE[ self::SESSION_COOKIE ] ?? null;
        $tokenParts = explode( '|', $source );
        $this -> token = [];
        if( count( $tokenParts ) == 2 )
        {
            $encoded = openssl_decrypt
            (
                base64_decode( $tokenParts[ 1 ]),
                $this -> getSslMethod(),
                $this -> getSslKey(),
                OPENSSL_RAW_DATA,
                base64_decode( $tokenParts[ 0 ])
            );

            $token = json_decode( $encoded, true );

            if
            (
                !empty( $token )
                && is_array( $token )
//              && not expare
            )
            {
                $this -> token = $token;
            }
        }

        /* Reset session if it is not valid */
        if( empty( $this -> token ))
        {
            $this -> reset();
        }

        return $this;
    }



    /*
        Return encripted session information - token
    */
    private function buildToken
    (
        int $aExpire = null
    )
    :string
    {
        /* Copy of array */
        $token = json_decode( json_encode( $this->token ), true);

        if( !empty( $aExpire ))
        {
            $token[ 't' ] = Moment::Create()
            -> now()
            -> add( $aExpire )
            -> get();
        }

        /* Create init vector */
        $initVector = openssl_random_pseudo_bytes
        (
            $this -> getSSLLengthVector()
        );

        return
        base64_encode( $initVector ) .
        '|' .
        base64_encode
        (
            openssl_encrypt
            (
                json_encode( $token ),
                $this -> getSSLMethod(),
                $this -> getSSLKey(),
                OPENSSL_RAW_DATA,
                $initVector
            )
        );
    }



    /*
        Set session data in to the cookie
    */
    public function send()
    :Session
    {
        /* return session ID to cookies */
        if ( !$this -> app -> isCli() )
        {
            setcookie
            (
                self::SESSION_COOKIE,
                $this -> buildToken(),
                [
                    'expires' =>  time() + $this -> getTtlSec(),
                    'path' => '/',
                    'secure' => false,
                    'httponly' => false,
                    'samesite' => 'Lax'
                ]
            );
        }
        return $this;
    }



    /**************************************************************************
        Operations with user data
    */

    /*
        Read session parameter form $AKey
    */
    public function get
    (
        array | string $aPath,
        $aDefault = null
    )
    {
        return clValueFromObject
        (
            $this -> token[ 'd' ],
            $aPath,
            $aDefault
        );
    }



    /*
        Write session parameter
    */
    public function set
    (
        /* Key name */
        $aPath,
        /* Value */
        $aValue = null
    )
    :Session
    {
        clValueToObject
        (
            $this -> token[ 'd' ],
            $aPath,
            $aValue
        );
        return $this;
    }



    /*
        Return the current session ID
    */
    public function getId()
    :string
    {
        return $this -> token[ 'i' ];
    }



    /*
        Set site value
    */
    public function setExpireAfter
    (
        /* Expare in microsecond from current moment */
        int $a
    )
    :Session
    {
        return $this -> set
        (
            't',
            Moment::create()
            -> now()
            -> add( $a )
            -> get()
        );
    }



    /*
        Set exparation moment
    */
    public function getExpireAfter()
    {
        return ((int) $this -> get( 't' )) - Moment::create() -> now() -> get();
    }



    /*
        Set login for current ssession
    */
    public function setLogin
    (
        /* Session user id */
        $a = self::USER_GUEST
    )
    :Session
    {
        $this -> token[ 'u' ] = $a;
        return $this;
    }



    /*
        Return the current login
     */
    public function getLogin
    (
        $aDefault = self::USER_GUEST
    )
    :string
    {
        return $this -> token[ 'u' ] ?? $aDefault;
    }



    /*
        Set the ssl method encription
    */
    public function setSslMethod( $a )
    :Session
    {
        $this -> sslMethod = $a;
        return $this;
    }



    /*
        return the ssl method encription
    */
    public function getSslMethod()
    :string
    {
        return $this -> sslMethod;
    }



    public function setSslKey
    (
        $a
    )
    :self
    {
        $this -> sslKey = $a;
        return $this;
    }



    public function getSslKey()
    :string
    {
        return $this -> sslKey;
    }



    public function setSslLengthVector
    (
        /* Ssl lenth vector*/
        int $a = 16
    )
    :self
    {
        $this -> sslLengthVector = $a;
        return $this;
    }


    /*
        Return the ssl length vector
    */
    public function getSslLengthVector()
    :int
    {
        return $this -> sslLengthVector;
    }



    /*
        Return token information
    */
    public function getToken()
    :array
    {
        return $this -> token;
    }



    /*
        Return session exparation time in seconds
    */
    public function getTtlSec()
    :int
    {
        return $this -> ttlSec;
    }



    /*
        Set session exparation time in seconds
    */
    public function setTtlSec
    (
        /* time to live in seconds */
        $a
    )
    :self
    {
        $this -> ttlSec = $a;
        return $this;
    }



    /*
        Return true if user is guest
    */
    public function isGuest()
    {
        return $this -> getLogin() == self::USER_GUEST;
    }
}
