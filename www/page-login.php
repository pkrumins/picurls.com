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

if (!$included_from_index) {
    header("Location: $SITE_URL");
    exit;
}

require_once 'mysmarty.php';
require_once 'system/db.sqlite.php';

$smarty = new MySmarty;

$smarty->assign('tpl_content',  'content-login-register.tpl.html');
$smarty->assign('page_style',   'style-login.css');

if ($_SERVER['REQUEST_METHOD'] != "POST") {
    $smarty->assign('page_title',       'login into picurls!');
    $smarty->assign('page_description', 'Login into picurls, buzziest pics on the net website!');
    $smarty->display('index.tpl.html');
    exit;
}

$error = false;
if (!isset($_POST['username'])) {
    $error = "No username was specified!";
}
else if (!isset($_POST['password'])) {
    $error = "No password was specified!";
}
else if (empty($_POST['username'])) {
    $error = "Username was left blank!";
}
else if (!preg_match("#^[a-zA-Z0-9]+$#", $_POST['username'])) {
    $error = "Username should consist only of upper or lower case letters and digits!";
    $smarty->assign('login_username', $_POST['username']);
}
else if (empty($_POST['password'])) {
    $error = "Password was left blank!";
    $smarty->assign('login_username', $_POST['username']);
}

if ($error) {
    $smarty->assign('login_error', $error);
    $smarty->display('index.tpl.html');
    exit;
}

$db = new SQLite($SQLITE_DB_PATH);

$query = "SELECT id, password, can_login FROM users WHERE username = '${_POST['username']}'";
$user_info = $db->fetchRowQueryAssoc($query);

if ($db->isError()) {
    $error = "Login failed! There was a database error: " . $db->getError();
    $smarty->assign('login_error', $error);
    $smarty->display('index.tpl.html');
    exit;
}

if (!$user_info) {
    $error = "Username '${_POST['username']}' does not exist!";
    $smarty->assign('login_error', $error);
    $smarty->assign('login_username', $_POST['username']);
    $smarty->display('index.tpl.html');
    exit;
}

if ($user_info['can_login'] == 0) {
    $error = "Your account has been disabled! Sorry...";
    $smarty->assign('login_error', $error);
    $smarty->display('index.tpl.html');
    exit;
}

$md5_pass = md5($_POST['password']);
if ($md5_pass == $user_info['password']) {
    # 
    $_SESSION['user_id']  = $user_info['id'];
    $_SESSION['username'] = $_POST['username'];

    setcookie('user_id',  $_SESSION['user_id'], time() + 3600*24*10); # expire after 10 days
    setcookie('md5_pass', $md5_pass,            time() + 3600*24*10);

    $time = time();
    $last_access_query = "UPDATE users set date_access = $time WHERE id = ${user_info['id']}";
    $db->query($last_access_query);

    header("Location: $SITE_URL");
}
else {
    $error = "Password incorrect for username '${_POST['username']}'!";
    $smarty->assign('login_error', $error);
    $smarty->assign('login_username', $_POST['username']);
    $smarty->display('index.tpl.html');
    exit;
}

?>
