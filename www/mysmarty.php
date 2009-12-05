<?php
/*
** Copyright (C) 2007 Peteris Krumins (peter@catonmat.net)
** http://www.catonmat.net  -  good coders code, great reuse
** 
** This program is free software: you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation, either version 3 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program.  If not, see <http://www.gnu.org/licenses/>.
**
** ------------------------------------------------------------------
**
** This code is part of http://picurls.com - buzziest pics website.
** Read how it was designed at:
** http://catonmat.net/blog/making-of-picurls-popurls-for-pictures-part-one/
**
*/ 

error_reporting(E_ALL);

require_once 'smarty/Smarty.class.php';

function smarty_modifier_commentize($comment) {
    # replace consecutive \n's with two <br>'s
    $comment = preg_replace("#\r?\n(\r?\n)+#", '<br><br>', $comment);

    # replace remaining \n's with a single <br>
    $comment = preg_replace("#\r?\n#", '<br>', $comment);

    return $comment;
}

function smarty_block_dynamic($param, $content, &$smarty) {
    return $content;
}


class MySmarty extends Smarty {
    function MySmarty() {
        global $BASE_PATH;  # UGLY!!!!!!!!!
        $this->Smarty();

        $this->template_dir = "$BASE_PATH/templates";
        $this->compile_dir  = "$BASE_PATH/templates_c";
        $this->cache_dir    = "$BASE_PATH/cache";

        $this->register_modifier('commentize', 'smarty_modifier_commentize');
        $this->register_block('dynamic', 'smarty_block_dynamic', false);
    }
}

?>
