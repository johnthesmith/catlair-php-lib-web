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
    Ползеная нагрузка веб движка.
    Основа для пользовательских контроллеров.

    Репозитории:
        Форк Pusa-Catlair https://github.com/johnthesmith/catlair-php-lib-web
        Пуса https://gitlab.com/catlair/pusa/-/tree/main
*/



/* Load web application library */
require_once LIB . '/web/web.php';

/* Load hub payload */
require_once LIB . '/app/hub.php';



/*
    Web payload class declaration
*/
class WebPayload extends Hub
{
    /* Payload content, initially empty */
    private $content = '';

    /* Content type of the payload */
    private $contentType = Web::HTML;

    /* Filename of the returned content */
    private $contentFileName = '';



    /**************************************************************************
        Utils
    */


    /*
        Mutate
    */
    public function mutate
    (
        /* Имя класса в который необходимо мутировать */
        string $aPayloadName
    )
    {
        $result = parent::mutate( $aPayloadName );

        /* Перенос контента */
        if( method_exists( $result, 'setContent' ))
        {
            $result -> setContent( $this -> getContent() );
        }

        /* Перенос типа контента */
        if( method_exists( $result, 'setContentType' ))
        {
            $result -> setContentType( $this -> getContentType() );
        }

        /* Перенос типа контента */
        if( method_exists( $result, 'setContentFileName' ))
        {
            $result -> setContentFileName( $this -> getContentFileName() );
        }
        return $result;
    }



    /*
        Unmutate
    */
    public function unmutate()
    {
        /* Return content */
        if( method_exists( $this -> getParent(), 'setContent' ))
        {
            $this -> getParent() -> setContent( $this -> getContent() );
        }

        /* Return type of content */
        if( method_exists( $this -> getParent(), 'setContentType' ))
        {
            $this -> getParent() -> setContentType
            (
                $this -> getContentType()
            );
        }

        /* Return content file name */
        if( method_exists( $this -> getParent(), 'setContentFileName' ))
        {
            $this -> getParent() -> setContentFileName
            (
                $this -> getContentFileName()
            );
        }

        /* Call parent unmutate */
        return parent::unmutate();
    }




//    /*
//        Запуск метода удаленной полезной нагрузки через REST
//        c использованием прямой ссылки и таймаута исполднения запроса
//    */
//    public function summonOnHost
//    (
//        /* Имя вызываемой полезной нагрузки */
//        string  $APayloadName,
//        /* Имя метода */
//        string  $APayloadMethod,
//        /* Дополнительные аргументы */
//        array   $AArguments,
//        /* Имя конфигураци с настройками */
//        string  $AURL,
//        /* Имя конфигураци с настройками */
//        string  $ARequestTimeoutMls,
//        /* Тип исполнтеля dispatcher или preceptor */
//        string $AType = 'dispatcher'
//    )
//    {
//        /* Формирование ссылки */
//        $callUrl = URL::Create()
//        -> parse( $AURL )
//        -> setPath([ 'api', $AType, 'exec' ])
//        -> clearParams()
//        ;
//
//        /* Исполнение запроса */
//        $bot = WebBot::create( $this -> getLog() )
//        -> setRequestTimeoutMls( $ARequestTimeoutMls )
//        -> setUrl( $callUrl )
//        -> setPostParam( 'payloadName'      , $APayloadName )
//        -> setPostParam( 'payloadMethod'    , $APayloadMethod )
//        -> setPostParam( 'payloadArguments' , $AArguments )
//        -> execute()
//        -> resultTo( $this )
//        ;
//
//        /*
//            Получние результата из текста ответа при положительном
//            состоянии после бота
//        */
//        $answer = null;
//        if( $this -> isOk() )
//        {
//            /* Анализ ответа */
//            if( $bot -> getContentType() == 'application/json' )
//            {
//                $answer = json_decode( $bot -> getAnswer(), true );
//                if( is_array( $answer ))
//                {
//                    /* Установка текущего состояния из ответа */
//                    $this -> setResultFromArray( $answer );
//                }
//            }
//            else
//            {
//                $answer = $bot -> getAnswer();
//            }
//        }
//
//        /* Возврат результата */
//        $this -> setContentType( $bot -> getContentType());
//        if
//        (
//            $this -> getContentType() == 'application/json' &&
//            is_array( $answer )
//        )
//        {
//            /* Возврат структурированного результата */
//            $this
//            -> getApp()
//            -> getOutcomeList()
//            -> setParams( clValueFromObject( $answer, 'Outcome' ));
//        }
//        else
//        {
//            /* Возврат контента */
//            $this -> setContent( $answer );
//        }
//
//        return $this;
//    }



//    /*
//        Запуск метода удаленной полезной нагрузки через REST протокол
//        c использованием конфигуратора
//    */
//    public function summon
//    (
//        /* Имя вызываемой полезной нагрузки */
//        string  $APayloadName,
//        /* Имя метода */
//        string  $APayloadMethod,
//        /* Дополнительные аргументы */
//        array   $AArguments = [],
//        /* Имя конфигураци с настройками */
//        string  $AConfig    = 'default'
//    )
//    {
//        /* Получение конфигурации удаленного сервера */
//        $config =
//        $this
//        -> getApp()
//        -> getParam([ 'web', 'remotePayloads', $AConfig ]);
//
//        if( empty( $config ))
//        {
//            $this -> setResult
//            (
//                'PayloadRemoteConfigIsEmpty',
//                [
//                    'congfig'   => $AConfig,
//                    'payload'   => $APayloadName,
//                    'method'    => $APayloadMethod
//                ]
//            );
//        }
//        else
//        {
//            $this -> summonOnHost
//            (
//                $APayloadName,
//                $APayloadMethod,
//                $AArguments,
//                clValueFromObject( $config, 'url', '127.0.0.1' ),
//                clValueFromObject( $config, 'requestTimeoutMls', 1000 ),
//                clValueFromObject( $config, 'type', 'dispatcher' )
//            );
//        }
//
//        return $this;
//    }



    /**************************************************************************
        File utils
    */

    /*
        Получение пути для файловой свалки сайта как то логи кэши и прочее
        Вебсервер должен обладать правами записи в указанную папку
    */
    public function getDumpPath
    (
        /* Локальный путь внутри сайта относительно возвращаемого пути */
        string $aLocal  = ''
    )
    {
        return $this -> getRwPath
        (
            'dump' . clLocalPath( $aLocal )
        );
    }



    /*
        Получение путей журналов
    */
    public function getLogPath
    (
        /* Локальный путь внутри сайта относительно возвращаемого пути */
        string $aLocal  = ''
    )
    {
        return
        $this -> getDumpPath
        (
            'log' . clLocalPath( $aLocal )
        );
    }



    /*
        Получение папки контента для проекта
    */
    public function getContentPath
    (
        string $aLocal  = '',
        string $aProject = null
    )
    {
        return
        $this -> getRoPath
        (
            'content' . clLocalPath( $aLocal ),
            $aProject
        );
    }



    /*
        Получение папки контекста
    */
    public function getContextPath
    (
        string $aLocal   = '',
        array $aContext = null,
        string $aProject = null
    )
    {
        $context = implode
        (
            '-',
            empty( $aContext ) ? $this -> getContext() : $aContext
        );
        return $this -> getContentPath
        (
            $context . clLocalPath( $aLocal ),
            $aProject
        );
    }



    /*
        Получение пути файла
    */
    public function getContentFile
    (
        string $aIdFile     = null,
        array $aContext     = [],
        string $aProject    = null,
        string $aLocal      = ''
    )
    {
        return $this -> getContextPath
        (
            $aIdFile . clLocalPath( $aLocal ),
            $aContext,
            $aProject
        );
    }




    /*
        Получение пути любого доступного файла
    */
    public function getContentFileAny
    (
        string $aIdFile     = null,
        array $aContext     = []
    )
    {
        /* Запрос перечня проектов */
        $projects = $this -> getApp() -> getProjects();
        foreach( $projects as $projectPath )
        {
            if( !empty( $projectPath ))
            {
                $file = $this -> getContentFile
                (
                    $aIdFile,
                    $aContext,
                    $projectPath
                );

                $result = realpath( $file );
                if( !empty( $result ))
                {
                    break;
                }
            }
        }
        return $result;
    }



    /**************************************************************************
        Utils
    */

    /*
        Return template content
    */
    public function getTemplate
    (
        string  $aId         = null,
        array   $aContext    = []
    )
    {
        $aContext = empty( $aContext ) ? $this -> getApp() -> getContext() : [];
        $file = $this -> getContentFileAny( $aId, $aContext );

        if( !empty( $file ))
        {
            $result = @file_get_contents( $file );
        }
        else
        {
            $result = 'Template ' .
            $aId .
            ' not found for context ' .
            implode( '-', $aContext );
        }
        return $result;
    }



    /**************************************************************************
        Setters and getters
    */

    public function getContext()
    {
        return $this -> getApp() -> getContext();
    }


    /*
        Return current content
    */
    public function getContent()
    {
        return $this -> content;
    }



    /*
        Set content
    */
    public function setContent
    (
        /* Content */
        $a = null
    )
    {
        $this -> content = $a;
        return $this;
    }



    /*
        Return current content
    */
    public function getContentType()
    {
        return $this -> contentType;
    }



    /*
        Set type of content
    */
    public function setContentType
    (
        /* Type of content */
        string $a = null
    )
    {
        $this -> contentType = $a;
        return $this;
    }



    /*
        Возвращается текущее имя файла
    */
    public function getContentFileName()
    {
        return $this -> contentFileName;
    }



    /*
        Установка текущего имени файла контента
    */
    public function setContentFileName
    (
        /* Устанавливаемое имя файла */
        string $a = null
    )
    {
        $this -> contentFileName = $a;
        return $this;
    }
}
