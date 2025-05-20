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
        Мутация полезной нагрузки
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



//    /*
//        Возвращает родителя мутанта
//    */
//    public function unmutate()
//    {
//        /* Перенос контента */
//        if( method_exists( $this -> getParent(), 'setContent' ))
//        {
//            $this -> getParent() -> setContent( $this -> getContent() );
//        }
//        /* Перенос типа контента */
//        if( method_exists( $this -> getParent(), 'setContentType' ))
//        {
//            $this -> getParent() -> setContentType
//            (
//                 $this -> getContentType()
//            );
//        }
//        /* Выполненеи родительского метода анмутации */
//        return parent::unmutate();
//    }



//    /*
//        Возвращает шаблон по идентификатору для сайта и языка
//    */
//    public function getTemplate
//    (
//        /* Идентификатор шаблона */
//        string $AID         = null,
//        /* Необязательный идентификатор сайта */
//        string $AIDSite     = Web::SITE_CURRENT,
//        /* Необязательный идентификатор языка */
//        string $AIDLanguage = null
//    )
//    {
//        return $this
//        -> getApp()
//        -> getTemplate( $AID, $AIDSite, $AIDLanguage );
//    }



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
        Setters and getters
    */



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
        /* Устанавливаемый конент */
        $a = null
    )
    {
        $this -> content = $a;
        return $this;
    }



    /*
        Возвращается текущий контент
    */
    public function getContentType()
    {
        return $this -> contentType;
    }



    /*
        Установка конетнта
    */
    public function setContentType
    (
        /* Устанавливаемый тип конента */
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
