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

define('PAGE_NAME', 'page-index');
define('CACHE_TIME', 60);

$unique_page_name = PAGE_NAME;

$smarty = new MySmarty;

if ($smarty->is_cached('index.tpl.html', $unique_page_name)) {
    $smarty->display('index.tpl.html', $unique_page_name);
    exit;
}

$db     = new SQLite($SQLITE_DB_PATH);

# find the sites to display
#
$sites = $db->fetchAllQueryAssoc('SELECT id, name, sane_name, url FROM sites WHERE visible = 1 ORDER BY priority ASC');
if ($db->isError()) {
    $smarty->assign('tpl_content', 'content-error.tpl.html');
    $smarty->assign('error', 'Database error has occured: ' . $db->getError());

    # TODO: figure out how not to cache a page
    $smarty->display('index.tpl.html');
    exit;
}

# Fetch ITEMS_PER_INDEX_PAGE for each site
# 
foreach ($sites as $site_idx => $site) {
    $item_query = "SELECT " . join(',', $ITEM_FIELDS) . " FROM items ".
                  "WHERE site_id = ${site['id']} AND visible = 1 ".
                  "ORDER BY date_added DESC, id DESC LIMIT $ITEMS_PER_INDEX_PAGE";
    $items = $db->fetchAllQueryAssoc($item_query, 'id');
    if ($db->isError()) {
        $smarty->assign('tpl_content', 'content-error.tpl.html');
        $smarty->assign('error', 'Database error has occured: ' . $db->getError());

        $smarty->display('index.tpl.html');

        exit;        
    }
    $sites[$site_idx]['items'] = $items;

    # Get comment count for each item.
    # Use a single query to get the count for all items.
    #
    $item_ids = Array();
    foreach ($items as $item_idx => $item) {
        array_push($item_ids, $items[$item_idx]['id']);

        # Initially set comment count to 0, and in the next piece of code
        # set the correct comment count by querying database.
        # 
        $sites[$site_idx]['items'][$item_idx]['comments']['count'] = 0;
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
        $sites[$site_idx]['items'][$cc['item_id']]['comments']['count'] = $cc['count'];
    }

    # Create escape fields specified $ESCAPE_FIELDS
    #
    # UPDATE: Smarty has an escape modifer
    #
    #foreach ($items as $item_idx => $item) {
    #    foreach ($ESCAPE_FIELDS as $type => $escapees) {
    #        foreach ($escapees as $escapee) {
    #            if ($type == "html") {
    #                $sites[$site_id]['_escaped'][$escapee] =
    #                    htmlentities($sites[$site_id]['items'][$item_idx][$escapee], ENT_QUOTES, 'UTF-8');
    #            }
    #            else if ($type == "url") {
    #                $sites[$site_id]['_escaped'][$escapee] =
    #                    urlencode($sites[$site_id]['items'][$item_idx][$escapee]);
    #            }
    #            else { /* TODO: ERROR, NO SUCH TYPE */ }
    #        }
    #    }
    #}
}

# Feed Smarty template engine with needed data
#
$smarty->assign('tpl_content', 'content-index.tpl.html');
$smarty->assign('page_style',  'style-index.css');

$smarty->assign_by_ref('sites', $sites);

# Generate the page!
#
$smarty->caching = 2;
$smarty->cache_lifetime = CACHE_TIME;
$smarty->display('index.tpl.html', $unique_page_name);

# If there was a welcome message right after registration,
# remove it so it got displayed just once.
#
if (isset($_SESSION['welcome'])) unset($_SESSION['welcome']);

?>
