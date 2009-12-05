#!/usr/bin/perl
#
# Copyright (C) 2007 Peteris Krumins (peter@catonmat.net)
# http://www.catonmat.net  -  good coders code, great reuse
# 
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.
#

use warnings;
use strict;

#
# This program takes images from tmp_items table of picurls.com db and
# tries to get a thumbnail. If successful, the image is copied from tmp_items
# to items table and the thumbnail is cached.
#

use DBI;
use POSIX;
use File::Basename;
use File::Flock;

use ThumbExtractor;
use ThumbMaker;
use ImageFinder;

# path to db
#
use constant DB_PATH => "/mnt/evms/services/apache/wwwroot/picurls/db/picurls.db";

# thumbnail path
use constant THUMB_PATH => "/mnt/evms/services/apache/wwwroot/picurls/www/thumbnails";

# relative thumbnail path to server root
#
use constant IMAGE_RELATIVE_WWW => "/thumbnails";

# items per cache dir
use constant ITEMS_PER_CACHE_DIR => 1000;

# how many tries to make to get thumbnail
use constant MAX_TRIES => 3;

# path to netpbm tools
#
use constant NETPBM_PATH => "/home/pkrumins/tmpinstall/netpbm-10.26.44/foobarbaz/bin";

# lockfile path
#
use constant LOCK_FILE_PATH => "/mnt/evms/services/apache/wwwroot/picurls/locks/pic_mover.lock";

#
#

lock_script();

#
#
my $dbh = DBI->connect("dbi:SQLite2:" . DB_PATH, '', '', { RaiseError => 1 });
$dbh->func(30*1000, 'busy_timeout'); # 30 sec timeout

#eleminate_old_tmp_pics();
my %entries = get_tmp_pics();

exit 0 unless keys %entries;

foreach my $ek (keys %entries) {
    my $entry = $entries{$ek};
    my ($sane_title, $thumb_local_path) = get_local_info($entry);

    my $thumb_uri = get_thumbnail($entry, $thumb_local_path);
    unless ($thumb_uri) { # something failed...
        inc_tries($entry);
        next;
    }

    move_pic($entry, $sane_title, $thumb_uri);
}

#
# move_pic
#
# moves pic entry from tmp_items to items
#
sub move_pic {
    my $entry = shift;
    my $sane_title = shift;
    my $thumb_uri = shift;

    my $id = $entry->{id};
    my $query =<<EOL;
INSERT INTO items (title, sane_title, url, thumb, site_id, date_added, visible)
VALUES (?, ?, ?, ?, ?, ?, 1)
EOL
    my $sth = $dbh->prepare($query);

    eval {
        $sth->execute($entry->{title}, $sane_title, $entry->{url}, $thumb_uri, $entry->{site_id}, $entry->{date_added});
    };
    unless ($@) {
        $dbh->do("DELETE FROM tmp_items WHERE id=$entry->{id}");
    }
    else {
        print STDERR "move_pic failed: $@\n";
    }
}

#
# inc_tries
#
# increments tries field in tmp_items table
#
sub inc_tries {
    my $entry = shift;

    my $id = $entry->{id};
    my $query = "UPDATE tmp_items SET tries = tries + 1 WHERE id=$id";
    $dbh->do($query);
}

#
# get_thumbnail
#
# gets a thumbnail of an entry and stores it at thumb_path and returns
# relative path to thumbnail www root dir.
# if thumbnail is available (like from flickr), do not store it, return
# full url to it instead.
#
sub get_thumbnail {
    my ($entry, $thumb_path) = @_;

    # copied 1:1 from page_gen.pl from digpicz.com project:
    # http://www.catonmat.net/blog/designing-digg-picture-website/
    #
    my $thex  = ThumbExtractor->new;
    my $thumb = $thex->get_thumbnail($entry->{url});

    my $rel_www_icon_path = IMAGE_RELATIVE_WWW . '/' . get_cache_subdir($entry->{id});
    $rel_www_icon_path .= '/' . basename($thumb_path);

    unless (defined $thumb) {
        my $image_finder = ImageFinder->new(netpbm => NETPBM_PATH);
        my $best_img = $image_finder->find_best_image($entry->{url});

        unless ($best_img) { # no best image, hmm.
            return undef;
        }

        # create a thumbnail for this image
        my $thumb_maker = ThumbMaker->new(netpbm  => NETPBM_PATH);
        my $success = $thumb_maker->create_thumbnail($best_img, $thumb_path,
            { width => 80, height => 80 });

        unlink $best_img;
        unless ($success) {
            return undef;
        }

        return $rel_www_icon_path;
    }

    if ($thumb->is_thumb) { # a real thumbnail
        return $thumb->url;
    }
    else { # just an image
        my $thumb_maker = ThumbMaker->new(netpbm  => NETPBM_PATH);
        my $success = $thumb_maker->create_thumbnail($thumb->url, $thumb_path,
            { width => 80, height => 80 });

        unless ($success) {
            return undef;
        }
    }
    return $rel_www_icon_path;        
}

#
# get_local_info
#
# given a picture entry, gets information for local usage
# (sane title and thumbnail path)
#
sub get_local_info { # todo: not a nice sub name
    my $entry = shift;

    my $sane_title = get_sane_title($entry->{title});
    my $id_subdir  = get_cache_subdir($entry->{id});

    my $thumb_prepath = THUMB_PATH . "/$id_subdir";
    my $thumb_path = "$thumb_prepath/$sane_title.jpg";
    unless (-d $thumb_prepath) {
        mkdir $thumb_prepath or die "Failed creating '$thumb_prepath': $!";
    }

    return ($sane_title, $thumb_path);
}

#
# get_cache_subdir
#
# given an integer ID, returns filesystem friendly dir computed by:
# [ ID / 1000 ] * 1000, where [ ] is integer part of a number
#
sub get_cache_subdir {
    my $id = shift;
    return (int $id / ITEMS_PER_CACHE_DIR) * ITEMS_PER_CACHE_DIR;
}

#
# get_sane_title
#
# given a story title, gets a sane title
#
sub get_sane_title {
    my $title = shift;
    my $sane_title_inc = 2;
    my $sane_title = sanitize_title($title);

    unless (length $sane_title) {
        $sane_title = "empty"
    }

    while (sane_title_exists($sane_title)) {
        # remove tailing -{num} which might have been added by the next line
        # if we looped more than once
        $sane_title =~ s/-\d+$//; 

        $sane_title .= '-' . $sane_title_inc++;
    }
    return $sane_title;
}

#
# sane_title_exists
#
# checks whether sane_title exists in the items table
#
sub sane_title_exists {
    my $title = shift;

    my $query = "SELECT COUNT(*) as count FROM items WHERE sane_title = ?";
    my $sthx = $dbh->prepare($query);
    $sthx->execute($title);
    my $hr = $sthx->fetchrow_hashref;

    return $hr->{count};
}

#
# get_tmp_pics
#
# gets temporary pics
#
sub get_tmp_pics {
    my $pic_query = "SELECT * from tmp_items WHERE tries <= " . MAX_TRIES;
    my $entries = $dbh->selectall_hashref($pic_query, 'id');

    return %$entries;
}

#
# eleminate_old_tmp_pics
#
# deletes all entries from tmp_items table which have tries > 3
#
sub eleminate_old_tmp_pics {
    $dbh->do("DELETE FROM tmp_items WHERE tries > " . MAX_TRIES);
}

#
# sanitize_title
#
# given a title of a story, the function sanitizes the title:
# removes [ ]'s, ( )'s, etc. and then replaces all non alphanumeric chars with '-'
#
sub sanitize_title {
    my $title = lc shift;

    $title =~ s{\[|\]|\(|\)|'}{}g;
    $title =~ s/[^[:alnum:]]/-/g;
    
    # get rid of multiple -'s
    $title =~ s/-{2,}/-/g;

    # get rid of leading and trailing -'s
    $title =~ s/^-+|-+$//g;

    if (length $title > 100) {
        $title = substr($title, 0, 100);
        $title =~ s/-*$//g; # there might now be one '-' at the end again
        $title =~ s/-[[:alnum:]]*$//g;
    }

    return $title;
}

#
# lock_script
#
# Exclusively locks a file, so we had always 1 copy of script running at any
# given moment
#
sub lock_script {
    my $ret = lock(LOCK_FILE_PATH, undef, 'nonblocking');
    unless ($ret) {
        print "Script already running. Quitting.\n";
        exit 1;
    }
}

