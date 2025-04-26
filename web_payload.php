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
    Форк Pusa-Catlair.


    https://gitlab.com/catlair/pusa/-/tree/main
*/

require_once LIB . '/app/payload.php';



class WebPayload extends Payload
{
    /* Тип контента полезной нагрузки */
    private $ContentType = null; // 'application/json';
    /* Контент полезной нагрузки, при старте пуст */
    private $Content = '';

    /*
        Создание модуля полезной нагрузки
        Возвращается объект полезной нагрузки
    */
    static public function create
    (
        App $AApp,                  /* Приложение */
        string $AClassName  = null, /* Имя класса полезной нагрузки*/
        string $ALibrary    = null  /* Имя библиотеки полезной нагрузки */
    )
    {
        return Payload::create( $AApp, $AClassName, $ALibrary );
    }



    /*
        Возвращается список
    */
    public function getOutcomeList()
    {
        return $this -> getApp() -> getOutcomeList();
    }



    /*
        Мутация полезной нагрузки
    */
    public function mutate
    (
        string $AClassName,         /* Имя класса в который необходимо мутировать */
        string $ALibrary = null     /* Не обязательная библиотека для загрузки */
    )
    {
        $result = parent::mutate( $AClassName, $ALibrary );
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
        return $result;
    }



    /*
        Возвращает родителя мутанта
    */
    public function unmutate()
    {
        /* Перенос контента */
        if( method_exists( $this -> getParent(), 'setContent' ))
        {
            $this -> getParent() -> setContent( $this -> getContent() );
        }
        /* Перенос типа контента */
        if( method_exists( $this -> getParent(), 'setContentType' ))
        {
            $this -> getParent() -> setContentType
            (
                 $this -> getContentType()
            );
        }
        /* Выполненеи родительского метода анмутации */
        return parent::unmutate();
    }



    /*
        Установка возвращаемых значений
        Используется метод приложения setOutcome
    */
    public function setOutcome
    (
        $AName,         /* Имя ключа или массив строк с путем ключа */
        $AValue = null
    )
    {
        $this -> getApp() -> setOutcome( $AName, $AValue );
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
        return $this -> getApp() -> getOutcome( $AName, $ADefault );
    }



    /*
        Возвращается текущий контент
    */
    public function getContent()
    {
        return $this -> Content;
    }



    /*
        Установка конетнта
    */
    public function setContent
    (
        $AValue = null     /* Устанавливаемый конент */
    )
    {
        switch( gettype( $AValue ))
        {
            case 'string': $this -> Content = $AValue; break;
            default: $this -> Content = json_encode( $AValue ); break;
        }
        return $this;
    }



    /*
        Возвращается текущий контент
    */
    public function getContentType()
    {
        return $this -> ContentType;
    }



    /*
        Установка конетнта
    */
    public function setContentType
    (
        string $AValue = null     /* Устанавливаемый тип конента */
    )
    {
        $this -> ContentType = $AValue;
        return $this;
    }





    /*
        Возвращает шаблон по идентификатору для сайта и языка
    */
    public function getTemplate
    (
        /* Идентификатор шаблона */
        string $AID         = null,
        /* Необязательный идентификатор сайта */
        string $AIDSite     = Web::SITE_CURRENT,
        /* Необязательный идентификатор языка */
        string $AIDLanguage = null
    )
    {
        return $this
        -> getApp()
        -> getTemplate( $AID, $AIDSite, $AIDLanguage );
    }



    /*
        Запуск метода удаленной полезной нагрузки через REST
        c использованием прямой ссылки и таймаута исполднения запроса
    */
    public function summonOnHost
    (
        /* Имя вызываемой полезной нагрузки */
        string  $APayloadName,
        /* Имя метода */
        string  $APayloadMethod,
        /* Дополнительные аргументы */
        array   $AArguments,
        /* Имя конфигураци с настройками */
        string  $AURL,
        /* Имя конфигураци с настройками */
        string  $ARequestTimeoutMls,
        /* Тип исполнтеля dispatcher или preceptor */
        string $AType = 'dispatcher'
    )
    {
        /* Формирование ссылки */
        $callUrl = URL::Create()
        -> parse( $AURL )
        -> setPath([ 'api', $AType, 'exec' ])
        -> clearParams()
        ;

        /* Исполнение запроса */
        $bot = WebBot::create( $this -> getLog() )
        -> setRequestTimeoutMls( $ARequestTimeoutMls )
        -> setUrl( $callUrl )
        -> setPostParam( 'payloadName'      , $APayloadName )
        -> setPostParam( 'payloadMethod'    , $APayloadMethod )
        -> setPostParam( 'payloadArguments' , $AArguments )
        -> execute()
        -> resultTo( $this )
        ;

        /*
            Получние результата из текста ответа при положительном
            состоянии после бота
        */
        $answer = null;
        if( $this -> isOk() )
        {
            /* Анализ ответа */
            if( $bot -> getContentType() == 'application/json' )
            {
                $answer = json_decode( $bot -> getAnswer(), true );
                if( is_array( $answer ))
                {
                    /* Установка текущего состояния из ответа */
                    $this -> setResultFromArray( $answer );
                }
            }
            else
            {
                $answer = $bot -> getAnswer();
            }
        }

        /* Возврат результата */
        $this -> setContentType( $bot -> getContentType());
        if
        (
            $this -> getContentType() == 'application/json' &&
            is_array( $answer )
        )
        {
            /* Возврат структурированного результата */
            $this
            -> getApp()
            -> getOutcomeList()
            -> setParams( clValueFromObject( $answer, 'Outcome' ));
        }
        else
        {
            /* Возврат контента */
            $this -> setContent( $answer );
        }

        return $this;
    }



    /*
        Запуск метода удаленной полезной нагрузки через REST протокол
        c использованием конфигуратора
    */
    public function summon
    (
        /* Имя вызываемой полезной нагрузки */
        string  $APayloadName,
        /* Имя метода */
        string  $APayloadMethod,
        /* Дополнительные аргументы */
        array   $AArguments = [],
        /* Имя конфигураци с настройками */
        string  $AConfig    = 'default'
    )
    {
        /* Получение конфигурации удаленного сервера */
        $config =
        $this
        -> getApp()
        -> getParam([ 'web', 'remotePayloads', $AConfig ]);

        if( empty( $config ))
        {
            $this -> setResult
            (
                'PayloadRemoteConfigIsEmpty',
                [
                    'congfig'   => $AConfig,
                    'payload'   => $APayloadName,
                    'method'    => $APayloadMethod
                ]
            );
        }
        else
        {
            $this -> summonOnHost
            (
                $APayloadName,
                $APayloadMethod,
                $AArguments,
                clValueFromObject( $config, 'url', '127.0.0.1' ),
                clValueFromObject( $config, 'requestTimeoutMls', 1000 ),
                clValueFromObject( $config, 'type', 'dispatcher' )
            );
        }

        return $this;
    }
}
