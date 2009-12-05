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
# This program takes as input the output of scraper.pl script and
# inserts the item data in tmp_items table of the database
#

use DBI;
use POSIX;

#
#

use constant DB_PATH => "/mnt/evms/services/apache/wwwroot/picurls/db/picurls.db";

#
#

binmode(STDIN, ':utf8');

my $dbh = DBI->connect("dbi:SQLite2:" . DB_PATH, '', '', { RaiseError => 1 });
$dbh->func(30*1000, 'busy_timeout'); # 30 sec timeout
my %sites = get_sites();


my $insert_query =<<EOL; 
INSERT INTO tmp_items (title, url, site_id, date_added, tries)
VALUES (?, ?, ?, ?, 0)
EOL
my $sth = $dbh->prepare($insert_query);

# paragraph slurp mode
$/ = '';

while (<>) {
    next if /^#/;
    parse_and_insert_db($_);
}

$sth = undef;
$dbh->disconnect;

#
# parse_and_insert_db
#
# parses a paragraph of scraper.pl output and puts the data in db
#
sub parse_and_insert_db {
    my $data = shift;

    my @lines = split /[\r\n]+/, $data;
    my %data;

    foreach (@lines) {
        my ($key, $val) = split ': ', $_, 2;
        $data{$key} = $val;
    }

    unless (exists $sites{$data{site}}) {
        print STDERR "Site '$data{site}' does not have a db entry in 'sites' table!\n";
        return;
    }

    # if the entry already exists in tmp_pictures or pictures dbs, don't insert it
    #
    return if entry_exists('items', %data);
    return if entry_exists('tmp_items', %data);

    my $date;
    unless (exists $data{unix_time}) {
        $date = time();
    }
    else {
        $date = $data{unix_time}
    }

    return unless exists $data{title}  && exists $data{url};

    $sth->execute($data{title}, $data{url}, $sites{$data{site}}->{id}, $date);
}

sub entry_exists {
    my ($table, %data) = @_;

    my $query = <<EOL;
SELECT COUNT(*) as count
FROM $table
WHERE title = ? AND url = ? and site_id = ?
EOL
    my $sthx = $dbh->prepare($query);
    $sthx->execute($data{title}, $data{url}, $sites{$data{site}}->{id});
    my $hr = $sthx->fetchrow_hashref;

    return $hr->{count};
}

sub get_sites {
    my $query = "SELECT id, sane_name as name FROM sites";
    my $data = $dbh->selectall_hashref($query, 'name');

    return %$data;
}
