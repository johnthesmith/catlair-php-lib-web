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

    /* Default content type of the payload */
    private $contentType = Mime::TXT;

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
        string $aPayloadName,
        /* Caller */
        string $aCaller = null
    )
    {
        $result = parent::mutate( $aPayloadName, $aCaller );

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
        string  $aId,
        string  $aContext   = ''
    )
    {
        /* Define result */
        $result = '';

        $aContext = empty( $aContext ) ? $this -> getContext() : '';
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



    public function paramsToContent
    (
        string $aType = 'json'
    )
    {
        switch( $aType )
        {
            case 'json':
                $this
                -> setContentType( MIME::JSON )
                -> setContent
                (
                    json_encode
                    (
                        $this -> getParams(),
                        JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES |
                        JSON_INVALID_UTF8_IGNORE |
                        JSON_PRETTY_PRINT
                    )
                );
            break;
        }
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
        -> setContentType( Mime::JSON );
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
        -> setOptimize( false )
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
    :string
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
        return empty($this -> contentType) ? MIME::TXT : $this -> contentType;
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
        string  $aLocal   = '',
        string  $aContext = '',
        string  $aProject = ''
    )
    {
        return $this -> getContentPath
        (
            $aContext . clLocalPath( $aLocal ),
            $aProject
        );
    }



    /*
        Получение пути файла
    */
    public function getContentFile
    (
        string $aIdFile,
        string $aContext    = '',
        string $aProject    = '',
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
        string  $aIdFile,
        string  $aContext = ''
    )
    {
        /* Обработка контекстов */
        $contexts = $this -> getApp() -> getDefaultContexts();
        if( $aContext !== '' )
        {
            $contexts = array_values
            (
                array_filter
                (
                    $contexts,
                    fn( $f ) => $f !== $aContext
                )
            );
            array_unshift( $contexts, $aContext );
        }

        /* Запрос перечня проектов */
        $projects = $this -> getApp() -> getProjects();

        /* Обход проектов для запроса пути */
        foreach( $projects as $projectPath )
        {
            if( !empty( $projectPath ))
            {
                foreach( $contexts as $context )
                {
                    $file = $this -> getContentFile
                    (
                        $aIdFile,
                        $context,
                        $projectPath
                    );
                    /* Get real path */
                    $result = realpath( $file );
                    if( !empty( $result ))
                    {
                        break 2;
                    }
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
        string $aContext = '',
        string $aProject = null
    )
    {
        return
        $this -> getRoPublicPath
        (
            'file/' . $aContext . clLocalPath( $aLocal ),
            $aProject
        );
    }



    /*
        Получение пути файла
    */
    public function getFile
    (
        string $aIdFile     = null,
        string $aContext    = '',
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
        string $aIdFile = null,
        string $aContext = ''
    )
    {
        /* Обработка контекстов */
        $contexts = $this -> getApp() -> getDefaultContexts();
        if( $aContext !== '' )
        {
            $contexts = array_values
            (
                array_filter
                (
                    $contexts,
                    fn( $f ) => $f !== $aContext
                )
            );
            array_unshift( $contexts, $aContext );
        }

        /* Запрос перечня проектов */
        $projects = $this -> getApp() -> getProjects();

        /* Обход проектов для запроса пути */
        foreach( $projects as $projectPath )
        {
            if( !empty( $projectPath ))
            {
                foreach( $contexts as $context )
                {
                    $file = $this -> getFile
                    (
                        $aIdFile,
                        $context,
                        $projectPath
                    );
                    /* Get real path */
                    $result = realpath( $file );
                    if( !empty( $result ))
                    {
                        break 2;
                    }
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




    /*
        Proxy request to another host
    */
    protected function proxy
    (
        /* Url  protocol://host:port */
        string $aUrl
    )
    : self
    {
        $this
        -> getLog()
        -> begin( 'proxy' )
        -> param( 'url', $aUrl )
        ;

        /* Создаем webbot */
        $bot = WebBot::create( $this -> getLog() )
        -> setRequestHeaders( $this -> getApp() -> getInHeaders() )
        -> setMethod( $_SERVER[ 'REQUEST_METHOD' ])
        -> setConnectTimeoutMls( 1000 )
        -> setRequestTimeoutMls( 1000 )
        -> setBody( file_get_contents( 'php://input' ))
        ;

        /* Создаём URL из переданного хоста */
        $bot
        -> getUrl()
        -> parse( $aUrl )
        -> setPath( $this -> getApp() -> getUrl() -> getPath() )
        -> setParams( $this -> getApp() -> getUrl() -> getParams() );

        $bot
        -> execute()
        -> resultTo( $this );
        if( $this -> isOk())
        {
            $this -> getApp() -> applyHeaders( $bot -> getResponseHeaders());
            $this -> setContent( $bot -> getContent());
            $this -> setContentType( $bot -> getResponseContentType());
        }

        $this -> getLog() -> end();

        return $this;
    }



    /**************************************************************************
        Механизм и вспомогательные интерфейся summon
        Вызов удаленных полезных нагрузок
    */



    /*
        Запаковывает текщее состояние пэйлода в summon contract
        meta.source - обязательное поля для контракта
    */
    public function toSummonContract
    (
        /* Перечень прикладных аргументов */
        array $aArgs = []
    )
    :array
    {
        return
        [
                    /* Метаинформация контракта */
            'meta' =>
            [
                /* Обязательный источник отправки контракта */
                'source' => get_class( $this )
            ],
            /* Результат контракта */
            'result' => $this -> getResultAsArray(),
            /* Контент */
            'content' => $this -> getContent(),
            /* Тип контента */
            'content-type' => $this -> getContentType(),
            /* Структура параметров состояний */
            'params' => $this -> getParams(),
            /* Аргументы к текущему вызвову контракта */
            'args' => $aArgs
        ];
    }



    /*
        Восстанавливает состояние пэйлода из summon contract
    */
    public function fromSummonContract
    (
        array $aContract
    ) :self
    {
        $this -> setContent( $aContract[ 'content' ] ?? '' );
        $this -> setContentType( $aContract[ 'content-type' ] ?? '' );
        $this -> setResultFromArray( $aContract[ 'result' ] ?? [] );
        $this -> setParams( $aContract[ 'params' ] ?? [] );
        return $this;
    }



    /*
        Расппковывает текств в текущее состояние
    */
    public function loadSummonState
    (
        /* Тушка ответа */
        string $aData,
        /* Тип контента при отсутсвии в json */
        string $aDefaultContentType = 'text/plain'
    )
    :self
    {
        $contract = Web::parseSummonContract( $aData, $aDefaultContentType );
        if( $contract !== null )
        {
            $this -> fromSummonContract( $contract );
        }
        else
        {
            $this -> setContent( $aData );
            $this -> setContentType( $aDefaultContentType );
        }

        return $this;
    }



    /*
        Запуск метода удаленной полезной нагрузки через REST
        Выполнение изменит result и content текущего объекта
    */
    public function summon
    (
        /* Имя вызываемой полезной нагрузки */
        string $aPayloadName,
        /* Имя метода */
        string $aPayloadMethod,
        /* Аргументы */
        array  $aArguments = [],
        /* Host */
        array $aHosts = []
    )
    :self
    {
        $this
        -> getLog()
        -> begin( 'summon' )
        -> param( 'payload', $aPayloadName )
        -> param( 'method', $aPayloadMethod )
        ;

        /* Определение маршрута локального */
        $route
        = $this -> readSummonPreferLocal()
        ? $this -> getApp() -> getRoute( $aPayloadName )
        : false;

        if( empty( $route ))
        {
            /* Чтение списка хостов */
            if( empty( $aHosts ))
            {
                $aHosts = $this -> readSummonHosts();
            }

            /* Сборка статистики по хостам */
            $stats = $this -> selectSummonStats( $aHosts, $aPayloadName );

            /* Чтение количества попыток перенаправления */
            $maxTries = $this -> readMaxSummonTry();

            /* Цикл попыток исполнения */
            $attempt = 0;
            while( $attempt < $maxTries )
            {
                $attempt++;
                /* Получение worker */
                $host = $this -> selectSummonHost( $stats );

                $this
                -> getLog()
                -> trace()
                -> param( 'attempt', $attempt )
                -> param( 'from', $maxTries )
                -> param( 'host', $host )
                ;

                /* Формирование тушки из контракта */
                $body = json_encode
                (
                    $this -> toSummonContract( $aArguments ),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );

                /* Формирование ссылки */
                $url = URL::Create()
                -> parse( $host . '/' . $aPayloadName . '/'. $aPayloadMethod );

                /* Извлекаем время исполнения предельное из конфига */
                $requestTimeout = $this -> readSummonRequestTimeout
                (
                    $aPayloadName,
                    $aPayloadMethod
                );

                /* Извлекаем время исполнения предельное из конфига */
                $connectTimeout = $this -> readSummonConnectTimeout
                (
                    $aPayloadName,
                    $aPayloadMethod
                );

                $this -> startSummonStat( $host, $aPayloadName );

                /* Исполнение запроса */
                $bot = WebBot::create( $this -> getLog() )
                -> setRequestTimeoutMls( $requestTimeout )
                -> setConnectTimeoutMls( $connectTimeout )
                -> setUrl( $url )
                -> setBody( $body )
                -> setMethod( 'POST' )
                -> setRequestHeaders( $this -> getApp() -> getInHeaders())
                -> execute()
                -> resultTo( $this );


                /* Проверяем состояние */
                if( $this -> isOk())
                {
                    $this -> getApp() -> applyHeaders( $bot -> getResponseHeaders());
                    $this -> loadSummonState
                    (
                        $bot -> getContent(),
                        $bot -> getResponseContentType()
                    );
                    /* Завершение цикла */
                    $attempt = $maxTries;
                }

                $this -> stopSummonStat( $host, $aPayloadName, $this -> getCode() );
            }
        }
        else
        {
            /* Локальыне вызовы */
            $this
            -> mutate( $aPayloadName )
            -> setContent( '' )
            -> call( $aPayloadMethod, $aArguments )
            -> unmutate();
        }

        $this -> getLog() -> end();
        return $this;
    }


    /*
        Выполняет вызов без передачи состояний текущего payload
        возвращается новый пэйлоад без состояний
    */
    public function invoke
    (
        string $aPayload,
        string $aMethod,
        array $aArgs = []
    )
    :self
    {
        return $this
        -> spawn()
        -> summon( $aPayload, $aMethod, $aArgs );
    }



    /*
        Чтение количества попыток перенаправления
    */
    public function readMaxSummonTry()
    : int
    {
        return (int) $this -> getApp() -> getParams()
        [ 'web' ][ 'summon' ][ 'try' ] ?? 2;
    }



    /*
        Возвращает время пределньго исполнения summon для payload
    */
    public function readSummonRequestTimeout
    (
        string $aPayload,
        string $aMethod
    )
    :int
    {
        $cfg = $this -> getApp() -> getParams()
        [ 'web' ]
        [ 'summon' ]
        [ 'request-timeout-mls' ] ?? [];

        if( !is_array( $cfg ))
        {
            return 1000;
        }
        else
        {
            return $cfg[ $aPayload . '/' . $aMethod ]
            ?? $cfg[ $aPayload ]
            ?? $cfg[ '*' ]
            ?? 1000;
        }
    }




    /*
        Возвращает время на подключение исполнения summon для payload
    */
    public function readSummonConnectTimeout
    (
        string $aPayload,
        string $aMethod
    )
    :int
    {
        $cfg = $this -> getApp() -> getParams()
        [ 'web' ]
        [ 'summon' ]
        [ 'connect-timeout-mls' ] ?? [];

        if( !is_array( $cfg ))
        {
            return 1000;
        }
        else
        {
            return $cfg[ $aPayload . '/' . $aMethod ]
            ?? $cfg[ $aPayload ]
            ?? $cfg[ '*' ]
            ?? 1000;
        }
    }



    /*
        Возваращает список узлов которые следует вызвать
        при summon и диспетчеризации
    */
    public function readSummonHosts()
    :array
    {
        $result = $this -> getApp()
        -> getParams()[ 'web' ][ 'summon' ][ 'hosts' ] ?? [];
        return is_array( $result ) ? $result : [];
    }



    /*
        Возваращает флаг предпочтения локальныого исполнения
        в случае если необходимый payload в наличии
    */
    public function readSummonPreferLocal()
    :bool
    {
        return $this -> getApp()
        -> getParams()[ 'web' ][ 'summon' ][ 'prefer-local' ] ?? false;
    }



    /*
        Возвращает по списку узлов статистику вызовов
        на них полезной нагрузки
    */
    public function selectSummonStats
    (
        /* Список узлов */
        array $hosts,
        /* Полезная нагрузка */
        string $payload
    )
    : array
    {
        $result = [];
        $now = (int)( microtime( true ) * 1000000 );

        /* Извлечение времени восстановления из конфига */
        $recoveryTimeSec = $this -> getApp() -> getParam
        (   [
                'web',
                'summon',
                'recovery-time-sec'
            ],
            10
        );

        /* Обход перечня хостов */
        foreach( $hosts as $host )
        {
            $stat = $this -> readSummonStat( $host, $payload );

            $start = $stat[ 'start' ] ?? 0;
            $stop = $stat[ 'stop' ] ?? 0;

            /* Длительность исполнения в миллисекундах */
            $duration = $stop - $start;
            $duration = $duration < 0 ? 0 : $duration;

            if( $duration > 0 )
            {
                $stat[ 'duration' ] = $duration;
            }

            $last = $stop ?: $now;
            $age = $now - $last;

            /* восстанавливается за 60 секунд */
            $recoveryTimeSec = 10;

            /* Штраф (penalty) — чем меньше, тем лучше */
            $penalty = 0;
            if( $duration > 0 )
            {
                $decayRate = $duration / ( $recoveryTimeSec * 1000000 );
                $penalty = $duration - $age * $decayRate;
            }

            if( $penalty < 1000 )
            {
                $penalty = rand( 0, 1000 );
            }

            $stat[ 'penalty' ] = $penalty;

            $result[ $host ] = $stat;
        }
        return $result;
    }



    /*
        Возвращает лучший хост для исполнения нагрузки на основе статистики
    */
    public function selectSummonHost
    (
        array $aStats
    )
    : string
    {
        /* Сортируем по penalty (от меньшего к большему) */
        uasort($aStats, fn($a, $b) => ($a['penalty'] ?? 0) - ($b['penalty'] ?? 0));
        /* Индекс по квадратному корню от случайного числа */
        $rand = lcg_value();
        $index = (int)( sqrt($rand) * count( $aStats ));
        /* Возвращаем ключ (host) по индексу */
        return array_keys( $aStats )[ $index ];
    }



    /*
        Возвращает массив статистики
    */
    public function readSummonStat
    (
        string $host,
        string $payload
    ): array
    {
        $key = 'stats_' . $payload . '_' . $host;
        $data = apcu_fetch( $key );
        return is_array($data) ? $data : [];
    }



    /*
        Запуск сбора статистики при исполнении внешнго вызова
    */
    public function startSummonStat
    (
        string $host,
        string $payload
    )
    : self
    {
        $key = 'stats_' . $payload . '_' . $host;
        $data = $this -> readSummonStat( $host, $payload );
        $data[ 'start' ] = (int)( microtime(true) * 1000000 );
        apcu_store($key, $data);
        return $this;
    }



    /*
        Остановка сбора статистики при исполнении внешнего вызова
        фактически запись статистики по результаатм.
    */
    public function stopSummonStat
    (
        string $host,
        string $payload,
        string $code
    )
    : self
    {
        $key = 'stats_' . $payload . '_' . $host;
        $data = $this -> readSummonStat( $host, $payload );
        $data[ 'count' ] = ( $data[ 'count' ] ?? 0) + 1;
        $data[ 'stop' ] = (int)( microtime(true) * 1000000 );
        $data[ 'code' ] = $code;
        apcu_store( $key, $data );
        return $this;
    }



    /*
        Очистка статистики вызовов для хоста и payload
    */
    public function clearSummonStat
    (
        string $host,
        string $payload
    )
    : self
    {
        $key = 'stats_' . $payload . '_' . $host;
        apcu_delete( $key );
        return $this;
    }



    /*
        Trace to http header
    */
    public function trace
    (
        string $aEvent,
        string $aLabel
    )
    :self
    {
        $this -> getApp() -> trace( $aEvent, $aLabel );
        return $this;
    }
}
