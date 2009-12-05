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

# Site URL
#
$SITE_URL = 'http://picurls.boom';

# Base path of picurls directory which contains
# template dir, cache dir, etc.
#
$BASE_PATH = '/mnt/evms/services/apache/wwwroot/picurls';

# SQLite database path. 
#
$SQLITE_DB_PATH = "$BASE_PATH/db/picurls.db";

# Number of latest pictures to display on the main (index) page
#
$ITEMS_PER_INDEX_PAGE = 6;

# Number of latest pictures to display on the site page
#
$ITEMS_PER_SITE_PAGE = 16;

# An item's fields which should be selected from the items table
#
$ITEM_FIELDS = Array('id', 'title', 'sane_title', 'url', 'thumb', 'site_id', 'date_added');

# Comments per my-comments page
#
$COMMENTS_PER_MY_COMMENTS = 16;

# Create additional escape (htmlentities/urlencode) for the following fields
#
# UPDATE: Not used because Smarty provides escape modifer!
#
#$ESCAPE_FIELDS = Array('html' => Array('title'),
#                       'url'  => Array('url'));

?>
