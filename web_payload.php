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


/* Load hub payload */
require_once LIB . '/app/hub.php';
/* Load web application library */
require_once LIB . '/web/web.php';
/* Load mime conversion library */
require_once LIB . '/web/mime.php';
/* Load web builder */
require_once LIB . '/web/web_builder.php';

require_once LIB . '/core/url.php';



/*
    Web payload class declaration
*/
class WebPayload extends Hub
{
    /* Payload content, initially empty */
    private $content = '';

    /* Content type of the payload */
    private $contentType = null;

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



    /*
        Return template content
    */
    public function getTemplate
    (
        /**/
        string  $aId         = null,
        array   $aContext    = []
    )
    {
        /* Define result */
        $result = '';

        $aContext = empty( $aContext ) ? $this -> getContext() : [];
        $file = $this -> getContentFileAny( $aId, $aContext );

        if( !empty( $file ))
        {
            $result = @file_get_contents( $file );
        }
        else
        {
            $this -> setResult
            (
                'template-not-found',
                [
                    'id' => $aId,
                    'context' => $aContext
                ]
            )
            -> backtrace()
            ;
            $this -> getApp() -> resultWarning( $this );
        }
        return $result;
    }



    /*
        Запуск метода удаленной полезной нагрузки через REST
        c использованием прямой ссылки и таймаута исполднения запроса
    */
    public function summon
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
//        /* Формирование ссылки */
//        $callUrl = URL::Create()
//        -> parse( $AURL )
//        -> setPath([ 'api', $AType, 'exec' ])
//        -> clearParams()
//        ;

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
        return $this;
    }



    /**************************************************************************
        Utils
    */



    /*
        Put result in to content
    */
    public function resultToContent()
    {
        return $this
        -> setContent( $this -> getResultAsArray() )
        -> setContentType( Web::JSON );
    }



    /*
        Convert array   [a,b,c,d]
        in to array     [ a => b, c => d ]
    */
    protected function getPathKeyValue
    (
        array $segments
    )
    : array
    {
        $result = [];
        for( $i = 0; $i < count( $segments ); $i += 2 )
        {
            $key = $segments[ $i ] ?? null;
            $val = $segments[ $i + 1 ] ?? null;
            if( $key !== null )
            {
                $result[ $key ] = $val;
            }
        }
        return $result;
    }



    /**************************************************************************
        Protected utility methods
    */


    /*
        Content builder
    */
    protected function buildContent
    (
        array $aArgs = []
    )
    {
        /* Create builder object */
        $builder = WebBuilder::create( $this );

        $builder
        -> setContent( $this -> getContent())
        -> setContentType( $this -> getContentType() )
        -> setIncome( clArrayAppend( $aArgs, $this -> getApp() -> getParams()))
        -> build()
        -> resultTo( $this )
        ;

        return $this
        -> setContentType( $builder -> getContentType())
        -> setContent( $builder -> getContent());
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
        $this -> getRoPrivatePath
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
        string  $aIdFile     = null,
        array   $aContext     = []
    )
    {
        /* Запрос перечня проектов */
        $projects = $this -> getApp() -> getProjects();

        /* Обход проектов для запроса пути */
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
                /* Log information */
                $this -> getLog()
                -> trace( 'Looking for content' )
                -> param( 'file', $file );
                /* Get real path */
                $result = realpath( $file );
                if( !empty( $result ))
                {
                    break;
                }
            }
        }
        return $result;
    }



    /*
        Получение папки файлов доступных для скачивания для проекта
    */
    public function getFilePath
    (
        string $aLocal = '',
        array $aContext = [],
        string $aProject = null
    )
    {
        $context = implode
        (
            '-',
            empty( $aContext ) ? $this -> getContext() : $aContext
        );

        return
        $this -> getRoPublicPath
        (
            'file/' . $context . clLocalPath( $aLocal ),
            $aProject
        );
    }



    /*
        Получение пути файла
    */
    public function getFile
    (
        string $aIdFile     = null,
        array $aContext     = [],
        string $aProject    = null,
        string $aLocal      = ''
    )
    {
        return $this -> getFilePath
        (
            $aIdFile . clLocalPath( $aLocal ),
            $aContext,
            $aProject
        );
    }



    /*
        Получение пути любого доступного файла
    */
    public function getFileAny
    (
        string  $aIdFile     = null,
        array   $aContext     = []
    )
    {
        /* Запрос перечня проектов */
        $projects = $this -> getApp() -> getProjects();

        /* Обход проектов для запроса пути */
        foreach( $projects as $projectPath )
        {
            if( !empty( $projectPath ))
            {
                $file = $this -> getFile
                (
                    $aIdFile,
                    $aContext,
                    $projectPath
                );
                /* Log information */
                $this -> getLog()
                -> trace( 'Looking for file' )
                -> param( 'file', $file );
                /* Get real path */
                $result = realpath( $file );
                if( !empty( $result ))
                {
                    break;
                }
            }
        }
        return $result;
    }



    /*
        Return application main url object
    */
    public function getUrl()
    :Url
    {
        return $this -> getApp() -> getUrl();
    }

}
