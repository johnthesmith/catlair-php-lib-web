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

    2025-06-01 still@itserv.ru
*/



namespace catlair;



class Mime
{
    /*
        Baisic content type
    */
    const HTML  = 'text/html';
    const PLAIN = 'text/plain';
    const JSON  = 'application/json';
    const TXT   = 'text/plan';
    const XML   = 'application/xml';
    const CSS   = 'text/css';
    const JS    = 'application/javascript';
    const YAML  = 'application/x-yaml';
    const SVG   = 'image/svg+xml';
    const PNG   = 'image/png';
    const BMP   = 'image/bmp';
    const JPG   = 'image/jpeg';
    const GIF   = 'image/gif';
    const ICO   = 'image/x-icon';



    private static array $mimeTypes =
    [
        /* Images */
        'svg'  => self::SVG,
        'png'  => self::PNG,
        'jpg'  => self::JPG,
        'jpeg' => self::JPG,
        'gif'  => self::GIF,
        'bmp'  => self::BMP,
        'webp' => 'image/webp',
        'ico'  => 'image/vnd.microsoft.icon',

        /* Documents */
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt'  => 'text/plan',
        'rtf'  => 'application/rtf',
        'odt'  => 'application/vnd.oasis.opendocument.text',

        /* Веб */
        'html' => self::HTML,
        'htm'  => self::HTML,
        'css'  => self::CSS,
        'js'   => self::JS,
        'json' => self::JSON,
        'yaml' => self::YAML,
        'xml'  => self::XML,
        'rss'  => 'application/rss+xml',

        /* Архивы */
        'zip'  => 'application/zip',
        'rar'  => 'application/vnd.rar',
        '7z'   => 'application/x-7z-compressed',
        'tar'  => 'application/x-tar',
        'gz'   => 'application/gzip',

        /* Видео и аудио */
        'mp3'  => 'audio/mpeg',
        'wav'  => 'audio/wav',
        'ogg'  => 'audio/ogg',
        'mp4'  => 'video/mp4',
        'mov'  => 'video/quicktime',
        'avi'  => 'video/x-msvideo',
        'mkv'  => 'video/x-matroska',

        /* Прочие */
        'csv'  => 'text/csv',
        'php'  => 'application/x-httpd-php',
        'exe'  => 'application/octet-stream',
        'bin'  => 'application/octet-stream',
    ];



    /*
        Return mime from file extention
    */
    public static function fromExt
    (
        /* file extention */
        string $ext
    )
    : string
    {
        return
        self::$mimeTypes[ strtolower( $ext ) ]
        ?? 'application/octet-stream';
    }
}
