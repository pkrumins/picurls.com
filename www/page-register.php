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
    $smarty->assign('page_title',       'register at picurls!');
    $smarty->assign('page_description', 'Become a registered member of picurls, the buzziest pics on the net website. No email required!');
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
    $smarty->assign('register_username', $_POST['username']);
}
else if (!isset($_POST['botspam']) || $_POST['botspam'] != 2) {
    $error = "Spam prevention riddle not solved!";
    $smarty->assign('register_username', $_POST['username']);
}
else if (empty($_POST['password'])) {
    $error = "Password was left blank!";
    $smarty->assign('register_username', $_POST['username']);
}
else if (empty($_POST['password2'])) {
    $error = "Verification password was left blank!";
    $smarty->assign('register_username', $_POST['username']);
}
else if ($_POST['password'] != $_POST['password2']) {
    $error = "Passwords do not match!";
    $smarty->assign('register_username', $_POST['username']);
}

if ($error) {
    $smarty->assign('registration_error', $error);
    $smarty->display('index.tpl.html');
    exit;
}

$db = new SQLite($SQLITE_DB_PATH);

$user_count_query = "SELECT COUNT(*) FROM users WHERE username = '${_POST['username']}'";
$user_count = $db->fetchRowQuerySingle($user_count_query);

if ($db->isError()) {
    $error = "Registration failed! There was a database error: " . $db->getError();
    $smarty->assign('registration_error', $error);
    $smarty->assign('register_username', $_POST['username']);
    $smarty->display('index.tpl.html');
    exit;
}

if ($user_count) {
    $error = "Username '${_POST['username']}' already exists!\n".
             "Please choose a different one!";
    $smarty->assign('registration_error', $error);
    $smarty->assign('register_username', $_POST['username']);
    $smarty->display('index.tpl.html');
    exit;
}

$current_time = time();
$md5_pass     = md5($_POST['password']);
$insert_query = "INSERT INTO users (username, password, date_regged, date_access, ip_address) VALUES ".
                "('${_POST['username']}', '$md5_pass', $current_time, $current_time, '${_SERVER['REMOTE_ADDR']}')";

$res = $db->query($insert_query);
if ($db->isError()) {
    $error = "Registration failed! There was a database error: " . $db->getError();
    $smarty->assign('registration_error', $error);
    $smarty->assign('register_username', $_POST['username']);
    $smarty->display('index.tpl.html');
    exit;
}

$_SESSION['user_id']  = $db->getLastInsertId();
$_SESSION['username'] = $_POST['username'];

setcookie('user_id',  $_SESSION['user_id'], time() + 3600*24*10); # expire after 10 days
setcookie('md5_pass', $md5_pass, time() + 3600*24*10);

# Display a welcome message to the user as she/he just got registered.
$_SESSION['welcome'] = 1;

header("Location: $SITE_URL");

?>
