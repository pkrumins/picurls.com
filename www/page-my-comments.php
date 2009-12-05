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
else if (!isset($_SESSION['user_id'])) {
    header("Location: $SITE_URL");
    exit;
}

require_once 'mysmarty.php';
require_once 'system/db.sqlite.php';

# Prepare human readable time difference between current time and comment time
# TODO: this was copy/pasted from page-item.php, move to common functions
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


$smarty = new MySmarty;
$db     = new SQLite($SQLITE_DB_PATH);

$smarty->assign('tpl_content',  'content-my-comments.tpl.html');
$smarty->assign('page_style',   'style-my-comments.css');

# Prepare data for navigation through pages [<prev] [1], [2], [3], etc, [next>]
#
$total_comments_query = "SELECT COUNT(*) FROM comments WHERE user_id = ${_SESSION['user_id']}";
$total_comments = $db->fetchRowQuerySingle($total_comments_query);
if ($db->isError()) {
    $smarty->assign('tpl_content', 'content-error.tpl.html');
    $smarty->assign('error', 'Database error has occured: ' . $db->getError());

    $smarty->display('index.tpl.html');
    exit;
}

$total_pages  = ceil($total_comments / $COMMENTS_PER_MY_COMMENTS);
$current_page = isset($HandlerMatches[1]) ? $HandlerMatches[1] : 1;

if ($current_page > $total_pages) {
    $current_page = 1;
}

# Fetch COMMENTS_PER_MY_COMMENTS comments for the current user
# 
$comment_offset = $COMMENTS_PER_MY_COMMENTS * ($current_page - 1);
$comment_query = <<<EOL
SELECT
  c.comment    comment,
  c.date_added date_added,
  u.username   username,
  i.title      item_title,
  i.sane_title item_sane_title,
  i.url        item_url
FROM comments c
LEFT JOIN items i
  ON c.item_id = i.id
LEFT JOIN users u
  ON c.user_id = u.id
WHERE user_id = ${_SESSION['user_id']}
ORDER BY c.date_added
DESC LIMIT $comment_offset, $COMMENTS_PER_MY_COMMENTS
EOL;
$comments = $db->fetchAllQueryAssoc($comment_query);
if ($db->isError()) {
    $smarty->assign('tpl_content', 'content-error.tpl.html');
    $smarty->assign('error', 'Database error has occured: ' . $db->getError());

    $smarty->display('index.tpl.html');

    exit;
}

foreach (array_keys($comments) as $c_key) {
    $comments[$c_key]['human_time_diff'] = human_time_diff($comments[$c_key]['date_added']);
}

$smarty->assign_by_ref('comments', $comments);
$smarty->assign('current_page', $current_page);
$smarty->assign('total_pages',  $total_pages);

$smarty->display('index.tpl.html');

?>
