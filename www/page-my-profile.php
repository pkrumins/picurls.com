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

# TODO: decouple profile from password changing (move to separate files)

if (!$included_from_index) {
    header("Location: $SITE_URL");
    exit;
}
else if (!isset($_SESSION['user_id'])) {
    header("Location: $SITE_URL");
    exit;
}

require_once 'mysmarty.php';
require_once 'system/db.sqlite.php';

$smarty = new MySmarty;
$db     = new SQLite($SQLITE_DB_PATH);

$smarty->assign('tpl_content',  'content-my-profile.tpl.html');
$smarty->assign('page_style',   'style-my-profile.css');

# Get the current information
#
$data_query = "SELECT data FROM users WHERE id = ${_SESSION['user_id']}";
$data_ser = $db->fetchRowQuerySingle($data_query);
if ($db->isError()) {
    $smarty->assign('tpl_content', 'content-error.tpl.html');
    $smarty->assign('error', 'There was a database error while getting your profile information: ' . $db->getError());

    $smarty->display('index.tpl.html');
    exit;        
}

$data = Array();
if ($data_ser) {
    $data = unserialize($data_ser);
}

$smarty->assign('email',   isset($data['email'])   ? $data['email']   : '');
$smarty->assign('website', isset($data['website']) ? $data['website'] : '');

# UGLINESS
if (isset($_POST['action'])) {
    if ($_POST['action'] == "profile") {
        $data['email']   = isset($_POST['email'])   ? $_POST['email']   : '';
        $data['website'] = isset($_POST['website']) ? $_POST['website'] : '';

        if ($data['email'] && !preg_match("#^.+@.+$#", $data['email'])) {
            $smarty->assign('error_profile', "Invalid email address '${data['email']}'!");
            $smarty->display('index.tpl.html');
            exit;
        }

        $data_esc_ser = $db->escape(serialize($data));

        $update_query = "UPDATE users SET data = '$data_esc_ser' WHERE id = ${_SESSION['user_id']}";
        if (!$db->query($update_query)) {
            $smarty->assign('error_profile', "There was a database error while updating your profile: " . $db->getError());
        }
        else {
            $smarty->assign('email',   $data['email']);
            $smarty->assign('website', $data['website']);
        }
    }
    else if ($_POST['action'] == "password") {
        $current_pass = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        $new_pass     = isset($_POST['new_password'])     ? $_POST['new_password']     : '';
        $new_pass2    = isset($_POST['new_password2'])    ? $_POST['new_password2']    : '';

        if (empty($current_pass)) {
            $smarty->assign('error_password', "To set a new password, enter your current password!");
            $smarty->display('index.tpl.html');
            exit;
        }
        
        # Check the existing password
        #
        $pass_query = "SELECT password FROM users WHERE id = ${_SESSION['user_id']}";
        $pass = $db->fetchRowQuerySingle($pass_query);
        if ($db->isError()) {
            $smarty->assign('error_password', 'There was a database error while getting your current password: ' . $db->getError());
            $smarty->display('index.tpl.html');
            exit;        
        }

        if (md5($current_pass) != $pass) {
            $smarty->assign('error_password', 'Your current password is incorrect!');
            $smarty->display('index.tpl.html');
            exit;
        }

        if (empty($new_pass) || empty($new_pass2)) {
            $smarty->assign('error_password', 'The new password can not be empty!');
            $smarty->display('index.tpl.html');
            exit;
        }

        if ($new_pass != $new_pass2) {
            $smarty->assign('error_password', 'The new password does not match the verification password. Try again!');
            $smarty->display('index.tpl.html');
            exit;
        }

        $md5_pass = md5($new_pass);
        $update_query = "UPDATE users SET password = '$md5_pass' WHERE id = ${_SESSION['user_id']}";
        if (!$db->query($update_query)) {
            $smarty->assign('error_password', "There was a database error while chaning your password: " . $db->getError());
            $smarty->display('index.tpl.html');
            exit;
        }
        else {
            $smarty->assign('info_password', 'The password has been successfully changed!');
        }
    }
}

$smarty->display('index.tpl.html');

?>
