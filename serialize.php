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
    Serialize method
    2026-01-03
*/



require_once LIB . '/web/mime.php';



function clSerialize
(
    /* Array with data */
    array $aData,
    /* Format: Mime::YAML || Mime::JSON */
    string $aFormat = Mime::JSON
)
:string
{
    switch( $aFormat )
    {
        case 'yaml':
        case 'yml':
        case Mime::YAML:
            return
            function_exists( 'yaml_emit' )
            ? yaml_emit
            (
                $aData,
                YAML_UTF8_ENCODING,
                YAML_LN_BREAK
            )
            : '';
        case 'json':
        case Mime::JSON:
            return json_encode
            (
                $aData,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            );
        default:
            return '';
    }
}
