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
    Движек Web сервера. Форк Pusa.
    https://gitlab.com/catlair/pusa/-/tree/main
    Наследуется от Engine. Расширяет приложение функционалом вебсервера.

    https://github.com/johnthesmith/catlair-php-lib-web
*/



require_once LIB . '/core/url.php';
require_once LIB . '/app/engine.php';

require_once 'web_builder.php';
require_once 'web_payload.php';



class Web extends Engine
{
    const   JSON                = 'application/json';
    const   HTML                = 'text/html';
    const   CSS                 = 'text/css';

    const   SITE_CURRENT        = 'site_current';
    const   SITE_DEFAULT        = 'site_default';
    const   LANGUAGE_DEFAULT    = 'en';
    const   LOCAL_HOST          = 'localhost';

    private $Content            = '';           /* Возвращаемый контент */
    private $IncomeList         = null;         /* Перечень аргументов */
    private $OutcomeList        = null;         /* Перечень возвращаемых значений */
    private $Url                = null;         /* Объект текущий URL */
    private $Optimize           = false;        /* Флаг итоговой оптимизации контента */


    function __construct()
    {
        ob_start();

        /* Создание списка аргументов */
        $this -> IncomeList = new Params();

        /* Создание списка исходящих параметров */
        $this -> OutcomeList = new Params();

        /* Возвращаемый контент */
        $this -> setContent( '' );

        parent::__construct();

        if( $this -> isOk() )
        {
            /*
                2. Настройка внутренних состояний атрибутов
            */

            /*
                2.1
                Настройка URL для метода getUrl
            */

            /*
                2.1.1
                Получение и установка при Uri наличии строки запроса
            */
            $Uri = clValueFromObject( $_SERVER, 'REQUEST_URI', '' );
            if( $Uri == '/' || empty( $Uri )) $Uri = $this -> getParam( 'default_uri', '' );

            /*  2.1.2
                Сборка URL
            */
            $this -> Url = URL::create()
            -> setScheme( clValueFromObject( $_SERVER, 'REQUEST_SCHEME', 'http' ) )
            -> setUser( '' /* TODO необходимо отправить юзера*/ )
            -> setPassword( '' /* TODO необходимо отправить пароль */ )
            -> setHost( $this -> getParam( 'domain', 'localhost' ) )
//            -> setPort( clValueFromObject( $_SERVER, 'SERVER_PORT' ))
            -> setUri( $Uri )
            ;

            /* Устанавлиаем путь умолчального проекта для случаев если компоненты не найдены в основном */
            $this -> setDefaultProjectPath
            (
                $this -> getParam( 'site_default_path', './default' )
            );

            /* Управленеи журналированием */

            /* Определение направления вывода лога */
            switch( php_sapi_name() )
            {
                case 'cli':
                    /* Лог выводится ранее определенным способом */
                break;
                default:
                    /* Лог выводится в файл */
                    $this
                    -> getLog()
                    -> setDestination( Log::FILE )
                    -> setLogPath( $this -> getSiteLogPath() . '/' . $_SERVER[ 'REMOTE_ADDR' ] )  /* Путь */
                    -> setLogFile( $this -> getUrl() -> toString() ); /* Файл */

                    if
                    (
                        $this -> getLog() -> getEnabled() &&
                        !clCheckPath( $this -> getLog() -> getLogPath() )
                    )
                    {
                        $this -> setResult
                        (
                            'FailedToCreateLogDirectory',
                            [
                                'CurrentPath'   => realpath( getcwd() ),
                                'LogPath'       => $this -> getLog() -> getLogPath(),
                                'DomainsPath'   => $this -> getParam( 'domains_path' ),
                            ]
                        );
                    }
                break;
            }
        }

        if( !$this -> isOk() )
        {
            $this -> returnContent();
        }
    }



    /*
        Создание объекта
    */
    static public function create()
    {
        return new Web();
    }



    /*
        Переопределенеи события конфигурирования
        Используется схема конфигурирования
        https://github.com/johnthesmith/scraps/blob/main/images/CatlairConf.jpg
    */
    public function onConfig()
    {
        /*
            Чтение аргументов из php://input и размещие их в POST
        */
        switch( clValueFromObject( $_SERVER, 'CONTENT_TYPE' ))
        {
            case self::JSON:
                $data = json_decode( file_get_contents( 'php://input' ), true );
                if( !empty( $data ))
                {
                    $_POST = array_merge( $_POST, $data );
                }
            break;
        }

        /*
            Выполнение родительского функционала
        */
        parent::onConfig();

        /*
            Сборка аргументов в приложении
        */
        $this
        -> addParams( $_COOKIE )
        -> addParams( $_POST )
        -> addParams( $_GET )
        /* -> loadSession() TODO */
        ;

        /*
            Созадание среза входящих аргументов.
            Далее с ними работают сборщики HTML (Builder).
            Пользователь будет видеть только то что он прислал.
        */
        $this -> IncomeList -> copyFrom( $this );

        /* Загрузка доменных данных */
        $this -> loadDomain();

        return $this;
    }



    /*
        Событие работы приложения
        Переопределение.
    */
    public function onWork()
    {
        if( $this -> isOk())
        {
            $PayloadName = $this -> getParam( 'payload' );
            if( empty( $PayloadName ))
            {
                /* Разбор URL */
                $Path = $this -> getURL() -> getPath();
                if( count( $Path ) > 0 )
                {
                    switch( $Path[ 0 ] )
                    {
                        case 'p':
                        case 'payload':
                        case $this -> getApiPayload():
                        {
                            /*
                                Установка вызова полезной нагрузки
                                Последний элемент массива используется в качестве имени метода
                                Все иные становятся путем для метода в каталоге полезной нагрузки
                            */
                            if( count( $Path ) > 2 )
                            {
                                array_shift( $Path );
                                $this
                                -> setParam( 'method', array_pop( $Path ))
                                -> setParam( 'payload', implode( '/', $Path ));
                            }
                            else
                            {
                                $this -> setResult( 'UnknownPayload' );
                            }
                            break;
                        }
                        case 'f':
                        case 'file':
                        {
                            /* TODO отправка файла */
                            array_shift( $Path );
                            break;
                        }
                        case 'c':
                        case 'content':
                        {
                            /*
                                Запрос контента с запуском билдера
                                Путь за исключением первого члена content используется для получения файла шаблона
                            */
                            array_shift( $Path );
                            $this
                            -> setContent( $this -> getTemplate( implode( '/', $Path ) ) )
                            -> setParam( 'method', null )
                            -> setParam( 'payload', null );
                            break;
                        }
                    }
                }
            }

            /* Получение имени полезной нагрузки */
            $PayloadName = $this -> getParam( 'payload' );
            if( !empty( $PayloadName ))
            {
                /* Получение метода для исполнения */
                $Method = $this -> getParam( 'method' );
                if( !empty( $Method ))
                {
                    /* Создание полезной нагрузки */
                    $payload = $this -> payload( $PayloadName );
                    $payload -> run( $Method ) -> resultTo( $this );
                    if( $this -> isOk())
                    {
                        if( method_exists( $payload, 'getContent'  ))
                        {
                            /* Возврат контента из полезной нагрузки */
                            if( $payload -> getContent() !== null )
                            {
                                $this -> setContent( $payload -> getContent() );
                            }
                            /* Возврат типа контента из полезной нагрузки */
                            if( $payload -> getContentType() !== null )
                            {
                                $this -> setContentType( $payload -> getContentType());
                            }
                        }
                    }
                }
                else
                {
                    $this
                    -> setResult( 'MethodIsEmpty', [ 'PayloadName' => $PayloadName, 'Method' => $Method ] )
                    -> resultWarning();
                }
            }
            /* Сборка контента */
            $this -> setContent
            (
                WebBuilder::createContent
                (
                    $this -> getContent(),
                    $this -> getOptimize(),
                    true,
                    $this
                )
            );
            $this -> returnContent();
        }
        return $this;
    }



    public function returnContent()
    {
        /* Автооопределение типа возвращаемого результата, если он не был явно определен ранее */
        if( empty( $this -> getContentType() ) )
        {
            $this -> setContentType( empty( $this -> getContent()) ? self::JSON : self::HTML );
        }

        /* Возвращается результирующий контент */
        switch( $this -> getContentType() )
        {
            case self::JSON:
                if( $this -> contentIsEmpty() )
                {
                    $Json =
                    [
                        'Result' =>
                        [
                            'Code'          => $this -> getCode(),
                            'Message'       => $this -> getMessage(),
                            'Detailes'      => $this -> getDetailes()
                        ],
                        'Outcome' => $this -> getOutcomeList() -> getParams(),
                    ];
                    $this -> setContent
                    (
                        json_encode
                        (
                            $Json,
                            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                        )
                    );
                }
            break;
        }
        header( 'Content-Type:' . $this -> getContentType() );

        /* Вывод контента */
        switch( php_sapi_name() )
        {
            case 'cli':
                /* Вывод контента для cli */
                $this -> getLog() -> prn( $this -> getContent(), 'Content' );
            break;
            default:
                /*
                    Если буффер пуст, выводим контент.
                    Любое наличие в буффере информации подавляет вывод контента.
                */
                if( empty( ob_get_contents() ))
                {
                    /* Вывод контента для fpm */
                    print_r( $this -> getContent() );
                }
            break;
        }
        return $this;
    }



    /*
        Создание модуля полезной нагрузки веб приложения
    */
    public function payload
    (
        string $AName   /* Имя модуля полезной нагрузки*/
    )
    {
        return WebPayload::create( $this, $AName );
    }



    /*
        Загрузка фала настроек домена
    */
    private function loadDomain()
    {
        /*
            1.1.

            Устанавливаем директорю для разрешения настроек через домен.
            Все домены, с которых может потенциально обратился вебсервер
            должны быть описаны в указанной папке.

            Папка с конфигурациями доменов может быть установлена
            настройкой nginx в файле /etc/nginx/sites-enabled/*
                location ~ \.php$ {
                        fastcgi_param DOMAINS_PATH "..";
                }
        */

        $this -> setParam
        (
            'domains_path',
            clValueFromObject( $_SERVER, 'DOMAINS_PATH', './domains' )
        );

        /*
            1.2.

            Определяем текущий домен
        */
        $this -> setDomain
        (
            clValueFromObject( $_SERVER, 'HTTP_HOST', $this -> getDomain() )
        );


        /*
            1.3.

            Загружаем конфигурационный файл домена и применяем параметры
            поверх GET POST
            мажорные параметры перепишут имеющиеся
            минорные будут добавлены только при отсутсвии мажорных
        */

        /* Получаем путь файла домена */
        $DomainFile = realpath( $this -> getDomainsPath( $this -> getDomain()) );

        $DomainFile
        = !empty( $DomainFile ) && file_exists( $DomainFile )
        ? $DomainFile
        : realpath( $this -> getDomainsPath( 'default' ));

        if( !empty( $DomainFile ) && file_exists( $DomainFile ))
        {
            $DomainData = json_decode( file_get_contents( $DomainFile ), true );
            if( empty( $DomainData ))
            {
                $this
                -> setResult
                (
                    'DomainConfigError',
                    [ 'Domain' =>  $this -> getDomain(), 'File' => $DomainFile ]
                )
                -> resultWarning();
            }
            else
            {
                $this
                /* Добавление мажорных настроке из файла домена, переписываются поверх */
                -> addParams( clValueFromObject( $DomainData, 'major', [] ) )
                /* Добавление минорных настроек из файла домена, добавляются если не существовали ранее */
                -> addParams( clValueFromObject( $DomainData, 'minor', [] ), true );
            }
        }
        else
        {
            $this
            -> setResult
            (
                'DomainNotFound',
                [ 'Domain' =>  $this -> getDomain() ]
            )
            -> resultWarning();
        }

        return $this;
    }




    /**************************************************************************
        Геттеры и сеттеры
    */

    /*
        Preparing Pusa object after creation
    */
    public function getURL()
    {
        return $this -> Url;
    }



    /*
        Возвращает идентификатор умолчального языка.
        Если не определен, возвращается LANGUAGE_DEFAULT.
    */
    public function getIDLanguageDefault()
    {
        return $this -> getParam( 'id_lang_default', self::LANGUAGE_DEFAULT );
    }



    /*
        Устанавливает идентификатор умолчального языка.
    */
    public function setIDLanguageDefault
    (
        string $AValue
    )
    {
        return $this -> setParam( 'id_lang_default', $AValue );
    }



    /*
        Возвращает текущий сайт.
        Если не определе, возвращается идентификатор умолчального сайта.
    */
    public function getIDLanguage()
    {
        return $this -> getParam( 'id_lang', $this -> getIDLanguageDefault() );
    }



    /*
        Устанавливает идентификатор текущего языка
    */
    public function setIDLanguage
    (
        string $AValue
    )
    {
        return $this -> setParam( 'id_lang', $AValue );
    }



    /*
        Взвращается список указателей
    */
    public function &getOutcomeList()
    {
        return $this -> OutcomeList;
    }



    /*
        Возвращает контет
    */
    public function getContent()
    {
        return $this -> Content;
    }



    /*
        Возвращает контет
    */
    public function contentIsEmpty()
    {
        return empty( $this -> Content );
    }



    /*
        Устанавливает контент
    */
    public function setContent
    (
        string $AValue = null
    )
    {
        $this -> Content = $AValue;
        return $this;
    }



    /*
        Устанавливает флаг итоговой оптимизации контента
    */
    public function setOptimize
    (
        string $AValue
    )
    {
        $this -> Optimize = $AValue;
        return $this;
    }



    /*
        Устанавливает контент
    */
    public function getOptimize()
    {
        return $this -> Optimize;
    }



    /*
        Возвращает текущий домен
    */
    public function getDomain()
    {
        return $this -> getParam( 'domain', '127.0.0.1' );
    }



    /*
        Устанавливает текущий домен
    */
    public function setDomain( $AValue )
    {
        return $this -> setParam( 'domain', $AValue );
    }



    /*
        Устанавливает тип возвращаемого контента
    */
    public function setContentType( $AValue )
    {
        $this -> setParam( 'content_type', $AValue );
        return $this;
    }



    /*
        Возвращает тип возвращаемого контента
    */
    public function getContentType()
    {
        return $this -> getParam( 'content_type',  null );
    }



    /*
        Установка возвращаемых значений
    */
    public function setOutcome
    (
        $AName,         /* Имя ключа или массив строк с путем ключа */
        $AValue = null
    )
    {
        $this -> getOutcomeList() -> setParam( $AName, $AValue );
        return $this;
    }



    /*
        Получение возвращаемых значений
    */
    public function getOutcome
    (
        string $AName,
        $ADefault = null
    )
    {
        return $this -> getOutcomeList() -> getParam( $AName, $ADefault );
    }



    /*
        Возвращается шаблон контента
    */
    public function getTemplate
    (
        string $AID         = null,                 /* Идентификатор шаблона */
        string $AIDSite     = Web::SITE_CURRENT,    /* Необязательный идентификатор сайта */
        string $AIDLanguage = null                  /* Необязательный идентифиатор языка */
    )
    {
        $AIDLanguage = empty( $AIDLanguage ) ? $this -> getIDLanguage() : $AIDLanguage;

        $File = $this -> getContentFileAny( $AID, $AIDSite, $AIDLanguage );

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
        Файловые пути
    */


    /*
        Получение папки хранилища доменов
    */
    public function getDomainsPath
    (
        string $ADomain = null, /* Имя домена */
        string $ALocal  = ''    /* Локальный путь внутри доменного дерева относительно возвращаемого пути */
    )
    {
        return $this
        -> getParam( 'domains_path' ) . '/' .
        $ADomain .
        $this -> getLocalPath( $ALocal );
    }



    /*
        Возвращает путь проекта по идентификатору сайта
        Аналог функции getProjectPath
    */
    public function getProjectPathBySite
    (
        string $AIDSite = Web::SITE_CURRENT /* Идентификатор сайта Web::SITE_CURRENT Web::SITE_DEFAULT */
    )
    {
        return
        $AIDSite == Web::SITE_CURRENT
        ? '.'
        : $this -> getParam( 'site_default_path' );
    }



    /*
        Получение пути по идентификатору сайта
    */
    public function getSitePath
    (
        string $AIDSite = Web::SITE_CURRENT,    /* Идентификатор сайта Web::SITE_CURRENT Web::SITE_DEFAULT */
        string $ALocal  = ''                    /* Локальный путь внутри сайта относительно возвращаемого пути */
    )
    {
        return
        $this -> getProjectPathBySite( $AIDSite ) .
        $this -> getLocalPath( $ALocal );
    }



    /*
        Получение пути для файловой свалки сайта как то логи кэши и прочее
        Вебсервер должен обладать правами записи в указанную папку
    */
    public function getSiteDumpPath
    (
        string $AIDSite = Web::SITE_CURRENT,    /* Идентификатор сайта Web::SITE_CURRENT Web::SITE_DEFAULT */
        string $ALocal  = ''                    /* Локальный путь внутри сайта относительно возвращаемого пути */
    )
    {
        return $this -> getSitePath( $AIDSite, 'dump' . $this -> getLocalPath( $ALocal ));
    }



    /*
        Получение путей журналов
    */
    public function getSiteLogPath
    (
        string $AIDSite = self::SITE_CURRENT,   /* Идентификатор сайта */
        string $ALocal  = ''                    /* Локальный путь внутри сайта относительно возвращаемого пути */
    )
    {
        return
        $this -> getSiteDumpPath
        (
            $AIDSite,
            'log' . $this -> getLocalPath( $ALocal )
        );
    }



    /*
        Получение папки контента для сайта по имени сайта
    */
    public function getContentPath
    (
        string $AIDSite = null,
        string $ALocal  = ''
    )
    {
        return
        $this -> getSitePath
        (
            $AIDSite,
            'content' . $this -> getLocalPath( $ALocal )
        );
    }



    /*
        Получение папки языкового контента для сайта
    */
    public function getLanguagePath
    (
        string $AIDSite     = null,
        string $AIDLanguage = null,
        string $ALocal      = ''
    )
    {
        $AIDLanguage = empty( $AIDLanguage ) ? $this -> getIDLanguage() : $AIDLanguage;
        return $this -> getContentPath( $AIDSite, $AIDLanguage . $this -> getLocalPath( $ALocal ));
    }



    /*
        Получение пути файла
    */
    public function getContentFile
    (
        string $AIDFile     = null,
        string $AIDSite     = null,
        string $AIDLanguage = null,
        string $ALocal      = ''
    )
    {
        return $this -> getLanguagePath( $AIDSite, $AIDLanguage, $AIDFile ) . $this -> getLocalPath( $ALocal );
    }



    /*
        Получение пути любого доступного файла
    */
    public function getContentFileAny
    (
        string $AIDFile     = null,
        string $AIDSite     = null,
        string $AIDLanguage = null,
        string $ALocal      = '',
        string $ARoot       = null
    )
    {
        $Result = clPathControl( $this -> getContentFile( $AIDFile, Web::SITE_CURRENT, $this -> getIDLanguage() ));
        if( !file_exists( $Result ))
        {
            $Result = clPathControl( $this -> getContentFile( $AIDFile, Web::SITE_CURRENT, $this -> getIDLanguageDefault() ));
            if( !file_exists( $Result ))
            {
                $Result = clPathControl( $this -> getContentFile( $AIDFile, Web::SITE_DEFAULT, $this -> getIDLanguage() ));
                if( !file_exists( $Result ))
                {
                    $Result = clPathControl( $this -> getContentFile( $AIDFile, Web::SITE_DEFAULT, $this -> getIDLanguageDefault() ));
                    if( !file_exists( $Result ))
                    {
                        $Result = null;
                    }
                }
            }
        }
        return $Result;
    }



    /*
        Возвращает имя API для секции URL
        http://domain/API/path/....
        Значение получается из параметра конфигурации api_payload
        При отсутствии такового возвращается умолчальное значение api
    */
    public function getApiPayload
    (
        string $aDefault = 'api'
    )
    :string
    {
        return $this -> getParam( 'api_payload', $aDefault );
    }
}

