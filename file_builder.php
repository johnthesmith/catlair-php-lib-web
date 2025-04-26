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

/*
    Сборщик файлов
    Расширяет функционал получения файла
    2022-07-07
*/



namespace catlair;



/*
    Локальные библиотеки
*/
require_once 'builder.php';
require_once 'log.php';



/*
    Класс сборщика
*/
class FileBuilder extends Builder
{
    private $AbsoluteTemplatePath   = null; /* Абсолютный путь для <cl content="folder/template"> */
    private $RelativeTemplatePath   = null; /* Относительный путь для <cl content="./folder/template"> */
    private $TemplateDefaultExt     = '';   /* Умолчальное расширение шаблона */


    /*
        Конструктор объекта
    */
    function __construct
    (
        Log $ALog,
        string $AAbsoluteTemplatePath = '',  /* Абсолютный путь для <cl content="folder/template"> */
        string $ARelativeTemplatePath = ''   /* Относительный путь для <cl content="./folder/template"> */
    )
    {
        parent::__construct( $ALog );
        $this -> AbsoluteTemplatePath = $AAbsoluteTemplatePath;
        $this -> RelativeTemplatePath = $ARelativeTemplatePath;
    }



    /*
        Создание объекта
        Рекомендуется использовать вместо конструктора
    */
    static public function create
    (
        Log $ALog,
        string $AbsoluteTemplatePath = '',  /* Абсолютный путь для <cl content="folder/template"> */
        string $RelativeTemplatePath = ''   /* Относительный путь для <cl content="./folder/template"> */
    )
    {
        return new FileBuilder
        (
            $ALog,
            $AbsoluteTemplatePath,
            $RelativeTemplatePath
        );
    }



    /*
        Return file path by part of path from relative path or absolute path.
    */
    public function getPathByLocal
    (
        string $ALocal
    )
    {
        /* Define file name by template identifier */
        return
        (
            strpos( $ALocal, './' ) === 0
            ? $this -> RelativeTemplatePath
            : $this -> AbsoluteTemplatePath
        ) . '/' . $ALocal;
    }



    /*
        Read end return file content by $AID:string
    */
    public function getTemplateContent
    (
        $AID,
        $ADefault   = null
    )
    {
        $FileName = $this -> getPathByLocal( $AID );

        /* Check the file extention */
        if( empty( pathinfo( $FileName, PATHINFO_EXTENSION ) ))
        {
            $FileName .= '.' . $this -> TemplateDefaultExt;
        }

        return file_exists( $FileName )
        ? @file_get_contents( $FileName )
        : 'Template ' . $AID . ' not found' . $FileName;
    }



    /*
        Установка умолчального расширения шаблона
    */
    public function setTemplateDefaultExt
    (
        $AValue
    )
    {
        $this -> TemplateDefaultExt = $AValue;
        return $this;
    }



    /*
        Extention for bulder functions
        Uses in Builder::Exec
    */
    public function extExec
    (
        &$AContent,
        $ACommand,
        $AValue,
        $AParams
    )
    {
        $Result = true;

        /* Buld list of files */
        switch( $ACommand )
        {
            case 'dir':
                if( $this -> IsOk())
                {
                    $Path = (string) ( $AValue ? $AValue : $Params[ 'path' ]);
                    $AContent = $this -> buildDir( $Path );
                }
            break;
            default:
                $Result = false;
            break;
        }
        return $Result;
    }


    /*
        Build directory list
    */
    public function buildDir
    (
        string $APath
    )
    {
        $ScanPath = $this -> getPathByLocal( $APath );
        if( is_dir( $ScanPath ))
        {
            /* Path is directory */
            $Files = [];
            $Dir = opendir( $ScanPath );

            while(( $File = readdir( $Dir )) !== false )
            {
                if(( $File != '.' ) && ( $File != '..' ) && ! is_dir(  $ScanPath . '/' . $File ) )
                {
                    array_push( $Files, $File );
                }
            }
            closedir( $Dir );

            /* Sort file list */
            sort( $Files );

            /* Build the list of links */
            $List = [];
            foreach( $Files as $File )
            {
                $lexemes = explode( '/', $File );
                $back = $lexemes[ count( $lexemes ) - 1];
                $points = explode( '.', $back );
                array_pop( $points );
                $filename = implode( '.', $points );
                array_push( $List, '- [' . $filename . '](' . $APath . '/' . $filename . ')' );
            }

            /* Build text */
            $Result = implode( PHP_EOL, $List );
        }
        else
        {
            $Result = 'Unknwon path "' . $APath . '"';
            $this -> setResult( 'UnknownPath', [ 'Path' => $ScanPath ], 'Unknown path' );
        }

        return $Result;
    }
}
