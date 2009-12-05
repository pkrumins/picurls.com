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

define('PAGE_NAME', 'page-item');
define('CACHE_TIME', 3600);

$sane_item_title = $HandlerMatches[1];

$unique_page_name = PAGE_NAME . "$sane_item_title";

$smarty = new MySmarty;

if ($smarty->is_cached('index.tpl.html', $unique_page_name)) {
    $smarty->display('index.tpl.html', $unique_page_name);
    exit;
}

$db     = new SQLite($SQLITE_DB_PATH);

$comment_error = false;
$add_comment = 0;
if (isset($_POST['sane_title'])) {
    if (!preg_match("#^[a-z0-9-]+$#", $_POST['sane_title']) ||
        $_POST['sane_title'] != $sane_item_title)
    {
        # Title must be well formatted and must match the requested URL
        $comment_error = "The item title was invalid. Is there some hackery going on?!";
        $smarty->assign('comment_error', $comment_error);
        $smarty->assign('existing_comment', isset($_POST['comment']) ? $_POST['comment'] : '');
    }
    else if (!isset($_POST['comment']) || empty($_POST['comment'])) {
        $comment_error = "No comment was typed. Please type what you wished and submit it again!";
        $smarty->assign('comment_error', $comment_error);
    }
    else if (!isset($_POST['botspam']) || $_POST['botspam'] != 2) {
        $comment_error = "No spam prevention code was entered!";
        $smarty->assign('comment_error', $comment_error);
        $smarty->assign('existing_comment', isset($_POST['comment']) ? $_POST['comment'] : '');
    }
    else if (preg_match("#^\s+$#", $_POST['comment'])) {
        $comment_error = "Your comment contained just empty spaces. Please type a better comment!";
        $smarty->assign('comment_error', $comment_error);
    }
    else {
        $add_comment = 1;
    }
}

# Check if the requested item exists.
#
# $HandlerMatches is a global array defined index.php where the
# request url was matched.
#
$escaped_item_title = $db->escape($sane_item_title);

# Fetch the item
# 
$item_query = "SELECT " . join(',', $ITEM_FIELDS) . " FROM items ".
              "WHERE sane_title = '${escaped_item_title}' AND visible = 1";
$item = $db->fetchRowQueryAssoc($item_query, 'id');
if ($db->isError()) {
    $smarty->assign('tpl_content', 'content-error.tpl.html');
    $smarty->assign('error', 'Database error has occured: ' . $db->getError());

    $smarty->display('index.tpl.html');

    exit;
}

if (!$item) {
    # The requested item was not found
    #
    $smarty->assign('tpl_content', 'content-error.tpl.html');
    $smarty->assign('error', "Item '" . htmlentities($HandlerMatches[1]) . "' does not exist!");

    $smarty->display('index.tpl.html');
    exit;
}

if ($add_comment) {
    $escaped_comment = $db->escape($_POST['comment']);
    $user_id         = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    $anonymous_name  = isset($_POST['name']) ? $db->escape($_POST['name']) : '';
    $time_now        = time();

    # Let's check if the cowboy is not posting too fast
    #
    $check_query = "SELECT date_added FROM comments ".
                   "WHERE item_id = ${item['id']} AND ".
                   "user_id = $user_id AND ".
                   "ip_address = '${_SERVER['REMOTE_ADDR']}' ".
                   "ORDER BY date_added DESC";
    $date_added = $db->fetchRowQuerySingle($check_query);
    if ($db->isError()) {
        $comment_error = "There was a database error while adding your comment: " . $db->getError() . "! Sorry...";
        $smarty->assign('comment_error', $comment_error);
        $smarty->assign('existing_comment', isset($_POST['comment']) ? $_POST['comment'] : '');
    }

    if ($date_added && ($time_now - $date_added) < 20) {
        $comment_error = "Please wait at least 20 seconds between posting comments! Thank you :)";
        $smarty->assign('comment_error', $comment_error);
        $smarty->assign('existing_comment', isset($_POST['comment']) ? $_POST['comment'] : '');
    }

    if (!$comment_error) {
        $add_comment_query = "INSERT INTO comments (comment, item_id, user_id, anonymous_name, date_added, ip_address) VALUES ".
            "('$escaped_comment', ${item['id']}, $user_id, '$anonymous_name', $time_now, '${_SERVER['REMOTE_ADDR']}')";

        if (!$db->query($add_comment_query)) {
            $comment_error = "There was a database error while adding your comment: " . $db->getError() . "! Sorry...";
            $smarty->assign('comment_error', $comment_error);
            $smarty->assign('existing_comment', isset($_POST['comment']) ? $_POST['comment'] : '');
        }
    }
}

# Get comments for this item
#
$comments_query = "SELECT c.id id, c.comment comment, c.date_added date_added, c.anonymous_name as anonymous_name, u.username username FROM comments c ".
                  "LEFT JOIN users u ON c.user_id = u.id WHERE c.item_id = ${item['id']} ORDER BY date_added ASC";
$comments = $db->fetchAllQueryAssoc($comments_query);
if ($db->isError()) {
    $smarty->assign('tpl_content', 'content-error.tpl.html');
    $smarty->assign('error', 'Database error has occured: ' . $db->getError());

    $smarty->display('index.tpl.html');

    exit;
}

$item['comments']['count'] = count($comments);

# Prepare human readable time difference between current time and comment time
#
function human_time_diff($date) {
    $time_diff = time() - $date;

    if ($time_diff < 3600) {
        $minutes = ceil($time_diff / 60);
        $human_diff = "$minutes minute" . ($minutes != 1 ? 's' : '');
    }
    else if ($time_diff >= 3600 && $time_diff < 3600*24) {
        $hours = ceil($time_diff / 3600);
        $human_diff = "$hours hour" . ($hours != 1 ? 's' : '');
    }
    else if ($time_diff >= 3600*24 && $time_diff < 3600*24*30) {
        $days = ceil($time_diff / 3600 / 24);
        $human_diff = "$days day" . ($days != 1 ? 's' : '');
    }
    else if ($time_diff >= 3600*24*30 && $time_diff < 3600*24*30*12) {
        $months = ceil($time_diff / 3600 / 24 / 30);
        $human_diff = "$month month" . ($months != 1 ? 's' : '');
    }
    else {
        $years = ceil($time_diff / 3600 / 24 / 30 / 12);
        $human_diff = "$years year" . ($years != 1 ? 's' : '');
    }

    return 'aprox. ' . $human_diff;
}

foreach (array_keys($comments) as $c_key) {
    $comments[$c_key]['human_time_diff'] = human_time_diff($comments[$c_key]['date_added']);
}

$item['comments']['items'] = $comments;

# Get site info (TODO: join on site table when fetching the item).
#
$site_query = "SELECT name, sane_name FROM sites WHERE id = ${item['site_id']}";
$site = $db->fetchRowQueryAssoc($site_query);

# Feed Smarty template engine with needed data
#
$smarty->assign('tpl_content',  'content-item.tpl.html');
$smarty->assign('page_style',   'style-item.css');

$smarty->assign('page_title',       $item['title']);
$smarty->assign('page_description', "buzziest pic on the net - ${item['title']}");

$smarty->assign_by_ref('item', $item);
$smarty->assign_by_ref('site', $site);

# Generate the page!
#
if ($add_comment || $comment_error) {
    # if we added a comment or there was an error, clear the cache
    $smarty->clear_cache('index.tpl.html', $unique_page_name);
}

if (!$comment_error) {
    $smarty->caching = 2;
    $smarty->cache_lifetime = CACHE_TIME;
}

$smarty->display('index.tpl.html', $unique_page_name);

?>
