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

session_start();

require_once 'config.php';

define('ERROR_PAGE', 'page-error.php');

# Fix the magic quote craziness
if (get_magic_quotes_gpc()) {
    function stripslashes_deep($value) {
        $value = is_array($value) ? array_map('stripslashes_deep', $value) : stripslashes($value);
        return $value;
    }
    
    $_POST   = array_map('stripslashes_deep', $_POST);
    $_GET    = array_map('stripslashes_deep', $_GET);
    $_COOKIE = array_map('stripslashes_deep', $_COOKIE);
}

# Let's see if the user had logged in previously but not in this session
#
if (isset($_COOKIE['user_id']) && isset($_COOKIE['md5_pass']) && !isset($_SESSION['user_id'])) {
    if (preg_match("#^\d+$#", $_COOKIE['user_id'])) {
        require_once 'system/db.sqlite.php';

        $db = new SQLite($SQLITE_DB_PATH);

        $query = "SELECT username, password, can_login FROM users WHERE id = ${_COOKIE['user_id']}";
        $user_info = $db->fetchRowQueryAssoc($query);

        if ($user_info && $user_info['can_login']) {
            if ($user_info['password'] == $_COOKIE['md5_pass']) {
                $_SESSION['user_id']  = $_COOKIE['user_id'];
                $_SESSION['username'] = $user_info['username'];

                $time = time();
                $last_access_query = "UPDATE users set date_access = $time WHERE id= ${_COOKIE['user_id']}";
                $db->query($last_access_query);
            }
        }
    }
}

$pages = Array(
    '#^/(?:index\.(?:html|php))?$#'  => 'page-index.php',       # main page handler
    '#^/site/(\w+)(?:-(\d+))?.html#' => 'page-site.php',        # site handler (digg, reddit etc)
    '#^/item/([a-z0-9-]+).html#'     => 'page-item.php',        # single item handler
    '#^/login.html#'                 => 'page-login.php',       # login page handler
    '#^/register.html#'              => 'page-register.php',    # registration page handler
    '#^/logout.html#'                => 'page-logout.php',      # logout page handler
    '#^/my-comments(?:-(\d+))?.html#'=> 'page-my-comments.php', # my comment page handler
    '#^/my-profile.html#'            => 'page-my-profile.php'   # my profile page handler
);

$parts = parse_url($_SERVER['REQUEST_URI']);
$path = preg_replace('#/+#', '/', $parts['path']); // drop multiple slashes

foreach ($pages as $page_pattern => $page) {
    if (preg_match($page_pattern, $path, $HandlerMatches)) {
        $included_from_index = true;
        require $page;
        exit;
    }
}

$included_from_index = true;
$error = "The requested page was not found!";
require ERROR_PAGE;

?>
