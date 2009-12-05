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

package ThumbExtractor;

#
# This package was written as a part of "digpicz: digg's missing picture section"
# website generator.
# This website can be viewed here: http://digpicz.com
#
# See http://www.catonmat.net/designing-digg-picture-website for more info.
#

#
# This package extracts thumbnail images for a given URL to a video or picture.
#

use LWP::UserAgent;
use HTML::Entities;
use HTML::TreeBuilder;
use File::MMagic;
use File::Temp 'mktemp';
use URI;

#
# Here are handlers for various video and image sites.
# There is no other way to extract thumbnail from a video site than analyzing the
# website how the site displays thumbnail itself.
#
# For video sites I wrote find_best_image in ImageCacher's package which finds
# the best image on the site.
#
# It is a very expensive function (requires fetching all images and converting them to
# pnm format and then calculate areas, etc).
#
# For the most popular sites (from top 10) I wrote handlers manually.
#
my @thumb_handlers = (
    'youtube.com'          => \&_youtube_handler,
    'video.google.com'     => \&_video_google_handler,
    'flickr.com'           => \&_flickr_handler, 
    'metacafe.com'         => \&_metacafe_handler,
    'liveleak.com'         => \&_liveleak_handler,
    'xkcd.com'             => \&_xkcd_handler,
    'bestpicever.com'      => \&_bestpicever_handler,
    'blogger.com'          => \&_blogger_handler
);

sub new {
    my $this = shift;
    my $class = ref($this) || $this;

    my $self = {};
    $self->{ua} = LWP::UserAgent->new(
        agent => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US) Gecko/20070515 Firefox/2.0.0.4',
        timeout => 5
    );

    bless $self, $class;
}

sub get_thumbnail {
    my ($self, $url) = @_;

    my $host = $self->_get_host($url);
    return undef if $host eq "unknown";

    # find a handler for a host
    for my $handler_idx (grep { $_ % 2 == 0 } 0 .. $#thumb_handlers) {
        if ($host =~ /$thumb_handlers[$handler_idx]/) {
            my $thumb = $thumb_handlers[$handler_idx + 1]->($url, $self->{ua});

            return $thumb;
        }
    }

    # there was no handler, try matching extensions
    my @img_rxes = qw|jpg$ jpeg$ gif$ png$|;
    my $rx = join '|', @img_rxes;

    if ($url =~ /$rx/i) {
        # some sites have URLs ending with an image extension but really it is
        # a HTML page. Let's check this.

        # read just the first KB of the image and make sure we are not getting
        # gzipped content
        #

        # File::MMagic is broken, it didnt work this way.

#        my $data;
#        my $cb_sub = sub {
#            $data .= shift;
#            my $length = do { use bytes; length($data) };
#
#            if ($length >= 2024) {
#                die "got a KB of data";
#            }
#        };
#        my $response = $self->{ua}->get($url, 'Accept-Encoding' => undef,
#            ':content_cb' => $cb_sub);

        my $tmp_file = $self->_get_temp_file();
        my $response = $self->{ua}->get($url, ':content_file' => $tmp_file);
        my $content = $response->content;
        my $mm = new File::MMagic;
        my $res = $mm->checktype_filename($tmp_file);
        unlink $tmp_file;

        if ($res =~ /image/) { # image, yumm, ok!
            return ThumbExtractor::Thumb->new($url, 0);
        }
    }

    return undef; # unknown url or not an image
}

sub _get_page {
    my ($ua, $url) = @_;
    my $resp = $ua->get($url);

    if ($resp->is_success) {
        return $resp->content;
    }
    return undef;
}

sub _get_temp_file {
    return mktemp("/tmp/imageTEXXXXXXXX");
}


#
# I use regexes for extracting because parsing each tree would be much slower and would
# take me 5 times longer to write the code and I can't see any reason to do it.
# I want the site to be running asap ;)
#

sub _youtube_handler {
    my $url = shift;

    # http://www.youtube.com/watch?v=qSNcVjpX-9Q&NR=1
    if ($url =~ /v=([A-Za-z0-9-_]+)/) {
        my $thumb_url = "http://img.youtube.com/vi/$1/1.jpg";
        return ThumbExtractor::Thumb->new($thumb_url, 1);
    }

    return undef;
}

sub _video_google_handler {
    my ($url, $ua) = @_;

    # google video can either have their own thumbnail or youtube's thumbnail
    #
    # <img src="http://video.google.com/ThumbnailServer2?app=vss&amp;contentid=e9201247d01caa03&amp;offsetms=930000&amp;itag=w160&amp;lang=en&amp;sigh=mD_ipxj1B87xnGNNMkRgont7Nb4"

    my $content = _get_page($ua, $url);
    if (defined $content) {
        my $thumb_url;
        if ($content =~ m{(http://video.google.com/ThumbnailServer2.*?")}) {
            $thumb_url = decode_entities $1;
        }
        elsif ($content =~ m{(http://img.youtube.com/vi/(?:[^/]+)/\d.jpg)}) {
            $thumb_url = $1;
        }
        else {
            return undef;
        }

        return ThumbExtractor::Thumb->new($thumb_url, 1);
    }
    return;
}

sub _flickr_handler {
    my ($url, $ua) = @_;

    my $flickr_extract = sub {
        my $id = shift;

        my $content = _get_page($ua, "http://flickr.com/photo_zoom.gne?id=$id&size=sq");
        if (defined $content) {
            if ($content =~ m{<a href="(http://farm\d+[^"]+)">Download}) {
                return ThumbExtractor::Thumb->new($1, 1);
            }
            return;
        }
        return;
    };

    if ($url =~ /static.flickr.com/) {
        return ThumbExtractor::Thumb->new($url, 0); # not a thumb yet
    }
    elsif ($url =~ /id=(\d+)/) {
        # http://flickr.com/photo_zoom.gne?id=346049991&size=sq
        return $flickr_extract->($1);
    }
    elsif ($url =~ m{/(\d+)/}) {
        # http://www.flickr.com/photos/kielbryant/118020322/in/set-72057594137096110/
        return $flickr_extract->($1);
    }

    return;
}

sub _metacafe_handler {
    my $url = shift;
    if ($url =~ m{metacafe.com/watch/(\d+)}) {
        return ThumbExtractor::Thumb->new("http://www.metacafe.com/thumb/$1.jpg", 1);
    }
    elsif ($url =~ m{metacafe.com/w/(\d+)}) {
        return ThumbExtractor::Thumb->new("http://www.metacafe.com/thumb/$1.jpg", 1);
    }
    return;
}


sub _liveleak_handler {
    my ($url, $ua) = @_;

    my $content = _get_page($ua, $url);
    if (defined $content) {
        if ($content =~ m{<link rel="videothumbnail" href="(.+?)" type="image/jpeg" />}) {
            return ThumbExtractor::Thumb->new($1, 1);
        }
    }

    return;
}

sub _xkcd_handler {
    my ($url, $ua) = @_;

    my $content = _get_page($ua, $url);
    return undef unless defined $content;

    if ($content =~ m{<img src="(http://imgs.xkcd.com/comics/(?:[^"]+))"}) {
        return ThumbExtractor::Thumb->new($1, 0);
    }

    return;
}

sub _bestpicever_handler {
    my ($url, $ua) = @_;

    my $content = _get_page($ua, $url);
    return undef unless defined $content;

    my $tree = HTML::TreeBuilder->new;
    $tree->parse($content);

    my $div_holding_img = $tree->look_down(_tag => 'div', id => 'img-holder-reg');
    unless (defined $div_holding_img) {
        $tree->delete;
        return undef;
    };

    my $img = $div_holding_img->look_down(_tag => 'img');
    unless (defined $img) {
        $tree->delete;
        return undef;
    }

    my $img_url = $img->attr('src');
    $tree->delete;
    return ThumbExtractor::Thumb->new($img_url, 0);
}

sub _blogger_handler {
    my ($url, $ua) = @_;

    my $content = _get_page($ua, $url);
    return undef unless defined $content;

#<html>
#<head>
#<title>gangsta.jpg (image)</title>
#<script type="text/javascript">
#<!--
#if (top.location != self.location) top.location = self.location;
#// -->
#</script>
#</head>
#<body bgcolor="#ffffff" text="#000000">
#<img src="http://bp2.blogger.com/_XNXLcHFsW1U/RsTNeXsvs8I/AAAAAAAABMY/EL6FxTMv_F0/s1600/gangsta.jpg" alt="[gangsta.jpg]" border=0>
#</body>
#</html>

    if ($content =~ /img src="(.+?)"/) {
        return ThumbExtractor::Thumb->new($1, 0);
    }
    
    return
}

sub _get_host {
    my ($self, $url) = @_;
    my $uri = URI->new($url);
    if ($uri->can('host')) {
        return $uri->host;
    }
    return "unknown";
}

package ThumbExtractor::Thumb;

sub new {
    my $class = shift;
    my ($url, $is_thumb) = @_;
    my $self = {
        url       => $url,
        is_thumb  => $is_thumb
    };

    bless $self, $class;
}

sub is_thumb {
    my $self = shift;
    return $self->{is_thumb};
}

sub url {
    my $self = shift;
    return $self->{url};
}

1;
