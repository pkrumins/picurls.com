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

package ThumbMaker;

#
# This package was written as a part of "reddit media: intelligent fun online"
# website generator.
# This website can be viewed here: http://redditmedia.com 
#
# See http://www.catonmat.net/designing-reddit-media-website for more info.
#

use warnings;
use strict;

use LWP::UserAgent;
use File::Temp 'mktemp';
use HTML::TreeBuilder;

use NetPbm;

sub new {
    my $this = shift;
    my $class = ref($this) || $this;

    my %opts = @_;

    my $self;
    $self->{netpbm} = NetPbm->new(netpbm => $opts{netpbm});
    $self->{ua} = LWP::UserAgent->new(
        agent => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US) Gecko/20070515 Firefox/2.0.0.4',
        timeout => 5
    );

    bless $self, $class;
}

#
# Given a path to local or external image, the function creates
# a jpeg thumbnail with dimensions of $params->{width} and $params->{height}
# and storores it at $out_path
#
sub create_thumbnail {
    my ($self, $img_path, $out_path, $params) = @_;

    if (-e $img_path) { # if it is a local file 
        return $self->_mkthumb($img_path, $out_path, $params);
    }

    my $tmp_img = $self->cache($img_path);
    if (defined $tmp_img) {
        my $ret = $self->_mkthumb($tmp_img, $out_path, $params);
        unlink $tmp_img;
        return $ret;
    }
}

sub cache {
    my $self = shift;
    my $url  = shift;  # cache image at $url

    my $temp_file = mktemp("/tmp/imageTMXXXXXXXX");
    my $resp = $self->{ua}->get($url, ":content_file" => $temp_file);
    unless ($resp->is_success) {
        unlink $temp_file;
        $self->{error} = "Failed getting '$url': " . $resp->status_line;
        return undef;
    }

    return $temp_file;
}

sub _mkthumb {
    my ($self, $img_path, $out_path, $params) = @_;
    my $netpbm = $self->{netpbm};

    # convert image to pnm format
    my $pnm_image = $netpbm->img2pnm($img_path);
    if ($netpbm->is_error) {
        $self->{error} = $netpbm->get_error;
        return 0;
    }

    # get image info
    my %img_info = $netpbm->get_img_info($pnm_image);
    if ($netpbm->is_error) {
        unlink $pnm_image;
        $self->{error} = $netpbm->get_error;
        return 0;
    }

    my %resize;
    if ($img_info{width} < $params->{width}) {
        $resize{w} = $params->{width};
    }
    if ($img_info{height} < $params->{height}) {
        $resize{h} = $params->{height};
    }

    my $resized_image;
    unless (keys %resize) {
        # if the image is bigger than the required dimensions (didn't need resizing)

        # cut out the middle of the image
#        my $middle_x = $img_info{width} / 2;
#        my $middle_y = $img_info{height} / 2;
#
#        my $x = int ($middle_x - ($params->{width} / 2));
#        my $y = int ($middle_y - ($params->{height} / 2));
#
#        my $cut_pnm_image = $self->_cut_img($pnm_image, $x, $y, $params->{width}, $params->{height});
#        unlink $pnm_image;
#        if ($self->is_error) {
#            return undef;
#        }
#
#        $pnm_image = $cut_pnm_image;

        #  ^^^^^^^^^^^ that didn't look nice, here is another idea
        #
        # this one finds the smallest dimension of image and resizes it to fit the
        # required dimensions, while keeping proportions of the other dimension
        #
        my ($new_width, $new_height);

        if ($img_info{width} < $img_info{height}) {
            ($new_width, $new_height) = ($params->{width}, 0);
        }
        elsif ($img_info{height} < $img_info{width}) {
            ($new_width, $new_height) = (0, $params->{height});
        }
        else { # width == height
            ($new_width, $new_height) = ($params->{width}, $params->{height});
        }

        $resized_image = $netpbm->resize_img($pnm_image, $new_width, $new_height);
    }
    else {
        # image is smaller than the required size of the thumbnail, stretch it
        $resized_image = $netpbm->resize_img($pnm_image, $resize{w} || 0, $resize{h} || 0);
    }

    if ($netpbm->is_error) {
        $self->{error} = $netpbm->get_error;
        return 0;
    }
    unlink $pnm_image;

    $pnm_image = $netpbm->cut_img($resized_image, 0, 0, $params->{width}, $params->{height});
    if ($netpbm->is_error) {
        $self->{error} = $netpbm->get_error;
        return 0;
    }
    unlink $resized_image;

    # add border
    if (exists $params->{border}) {
        my $bordered_image = $netpbm->border_img($pnm_image, $params->{border},
            $params->{border_color} || '#000');

        unless ($netpbm->is_error) {
            unlink $pnm_image;
            $pnm_image = $bordered_image;
        }
    }

    $netpbm->pnm2jpg($pnm_image, $out_path);
    if ($netpbm->is_error) {
        $self->{error} = $netpbm->get_error;
        return 0;
    }
    unlink $pnm_image;

    return 1;
}

sub is_error {
    my $self = shift;

    return 1 if exists $self->{error};
    return 0;
}

sub get_error {
    return shift->{error}
}


1;
