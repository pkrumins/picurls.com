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

define('PAGE_NAME', 'page-site');
define('CACHE_TIME', 60);

$site_name    = $HandlerMatches[1];
$current_page = isset($HandlerMatches[2]) ? $HandlerMatches[2] : 1;

$unique_page_name = PAGE_NAME . "$site_name-$current_page";

$smarty = new MySmarty;

if ($smarty->is_cached('index.tpl.html', $unique_page_name)) {
    $smarty->display('index.tpl.html', $unique_page_name);
    exit;
}

$db     = new SQLite($SQLITE_DB_PATH);

# Check if the requested site exists.
#
# $HandlerMatches is a global array defined index.php where the
# request url was matched.
#
$escaped_site = $db->escape($HandlerMatches[1]);

# find the sites to display
# TODO: cache this (because it changes very rarely)
#
$site = $db->fetchRowQueryAssoc("SELECT id, name, sane_name, url FROM sites WHERE sane_name = '$escaped_site' AND visible = 1");
if ($db->isError()) {
    $smarty->assign('tpl_content', 'content-error.tpl.html');
    $smarty->assign('error', 'Database error has occured: ' . $db->getError());

    $smarty->display('index.tpl.html');
    exit;
}

if (!$site) {
    # The requested site was not found
    #
    $smarty->assign('tpl_content', 'content-error.tpl.html');
    $smarty->assign('error', "Pictures from '" . htmlentities($HandlerMatches[1]) . "' are not being collected!");

    $smarty->display('index.tpl.html');
    exit;
}

# Prepare data for navigation through pages [<prev] [1], [2], [3], etc, [next>]
#
$total_items_query = "SELECT COUNT(*) FROM items WHERE site_id = ${site['id']}";
$total_items = $db->fetchRowQuerySingle($total_items_query);
if ($db->isError()) {
    $smarty->assign('tpl_content', 'content-error.tpl.html');
    $smarty->assign('error', 'Database error has occured: ' . $db->getError());

    $smarty->display('index.tpl.html');
    exit;
}

$total_pages = ceil($total_items / $ITEMS_PER_SITE_PAGE);
$current_page = isset($HandlerMatches[2]) ? $HandlerMatches[2] : 1;

if ($current_page > $total_pages) {
    $current_page = 1;
}

# Fetch ITEMS_PER_INDEX_PAGE for each site
# 
$item_offset = $ITEMS_PER_SITE_PAGE * ($current_page - 1);
$item_query = "SELECT " . join(',', $ITEM_FIELDS) . " FROM items ".
              "WHERE site_id = ${site['id']} AND visible = 1 ".
              "ORDER BY date_added DESC, id DESC LIMIT $item_offset, $ITEMS_PER_SITE_PAGE";
$items = $db->fetchAllQueryAssoc($item_query, 'id');
if ($db->isError()) {
    $smarty->assign('tpl_content', 'content-error.tpl.html');
    $smarty->assign('error', 'Database error has occured: ' . $db->getError());

    $smarty->display('index.tpl.html');

    exit;
}
$site['items'] = $items;

# Get comment count for each item.
# Use a single query to get the count for all items.
#
$item_ids = Array();
foreach ($items as $item_idx => $item) {
    array_push($item_ids, $items[$item_idx]['id']);

    # Initially set comment count to 0, and in the next piece of code
    # set the correct comment count by querying database.
    # 
    $site['items'][$item_idx]['comments']['count'] = 0;
}

$comment_count_query = "SELECT item_id, COUNT(*) as count FROM comments WHERE item_id IN ".
                       "(". join(',', $item_ids) .") GROUP by item_id";
$comment_count = $db->fetchAllQueryAssoc($comment_count_query);
if ($db->isError()) {
    $smarty->assign('tpl_content', 'content-error.tpl.html');
    $smarty->assign('error', 'Database error has occured: ' . $db->getError());

    $smarty->display('index.tpl.html');

    exit;        
}

foreach ($comment_count as $c_idx => $cc) {
    $site['items'][$cc['item_id']]['comments']['count'] = $cc['count'];
}

# Feed Smarty template engine with needed data
#
$smarty->assign('tpl_content',  'content-site.tpl.html');
$smarty->assign('page_style',   'style-page.css');
$smarty->assign('page_title',       "buzziest pics from ${site['name']}!");
$smarty->assign('page_description', "these are the buzziest pictures on the net from ${site['name']}!");
$smarty->assign('current_page', $current_page);
$smarty->assign('total_pages',  $total_pages);

$smarty->assign_by_ref('site', $site);

# Generate the page!
#
$smarty->caching = 2;
$smarty->cache_lifetime = CACHE_TIME;
$smarty->display('index.tpl.html', $unique_page_name);

?>
