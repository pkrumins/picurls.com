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

package NetPbm;

#
# This package was written as a part of "reddit media: intelligent fun online"
# website generator.
# This website can be viewed here: http://redditmedia.com 
#
# See http://www.catonmat.net/designing-reddit-media-website for more info.
#

use warnings;
use strict;

#
# Package for manipulating images.
#

use File::Temp 'mktemp';
use File::MMagic;

sub new {
    my ($this, %args) = @_;
    my $class = ref($this) || $this;

    my $self = { conf => \%args };
    bless $self, $class;
}

#
# get_img_info
#
# Given a path to an image, returns a hash with its primitive info
# (width and height as keys)
# On error, returns an empty hash.
#
sub get_img_info {
    my ($self, $img) = @_;

    my $info_line = $self->_run_netpbm_and_slurp("pamfile", $img);
    if ($self->is_error) {
        $self->set_error("Failed getting image info for '$img': " . $self->get_error);
        return;
    }

    if ($info_line =~ /(\d+) by (\d+)/) {
        return (width => $1, height => $2);
    }

    return
}

#
# _get_file_mime
#
# Given a path to file, returns it's type in mime format based on magic
#
sub _get_file_mime {
    my ($self, $file_path) = @_;

    my $mm = new File::MMagic;
    my $type = $mm->checktype_filename($file_path);

    return $type;
}

#
# get_img_type
#
# Given a path to an image, returns its type based on magic
#
sub get_img_type {
    my ($self, $img_path) = @_;

    my $type = $self->_get_file_mime($img_path);
    if ($type =~ m{image/(.+)}) { # image, yumm, ok!
        if ($1 =~ /x-portable/) { # portable pbm image
            return "pnm"
        }
        return $1;
    }

    return "unknown";
}

#
# border_img
#
# Given a path to an image, adds a border with $border_size width and $color
#
sub border_img {
    my ($self, $img, $border_size, $color) = @_;

    # taken from pnmmargin bash script
    #
    # ppmmake $color $size 1 > $tmp2
    # pamflip -rotate90 $tmp2 > $tmp3
    # pnmcat -lr $tmp2 $tmp1 $tmp2 > $tmp4
    # pnmcat -tb $tmp3 $tmp4 $tmp3
    #

    my $tmp2 = $self->_get_temp_file;
    my @ppmmake = ("'$color'", $border_size, 1);
    $self->_run_netpbm_and_redirect("ppmmake", @ppmmake, $tmp2);
    if ($self->is_error) {
        $self->set_error("Failed adding a border to '$img': " . $self->get_error);
        return undef;
    }

    my $tmp3 = $self->_get_temp_file;
    my @pamflip = ("-rotate90", $tmp2);
    $self->_run_netpbm_and_redirect("pamflip", @pamflip, $tmp3);
    if ($self->is_error) {
        $self->set_error("Failed adding a border to '$img': " . $self->get_error);
        return undef;
    }

    my $tmp4 = $self->_get_temp_file;
    my @pamcat = ("-lr", $tmp2, $img, $tmp2);
    $self->_run_netpbm_and_redirect("pnmcat", @pamcat, $tmp4);
    if ($self->is_error) {
        $self->set_error("Failed adding a border to '$img': " . $self->get_error);
        return undef;
    }

    my $bordered_img = $self->_get_temp_file;
    @pamcat = ("-tb", $tmp3, $tmp4, $tmp3);
    $self->_run_netpbm_and_redirect("pnmcat", @pamcat, $bordered_img);
    if ($self->is_error) {
        $self->set_error("Failed adding a border to '$img': " . $self->get_error);
        return undef;
    }

    unlink $tmp2, $tmp3, $tmp4;

    return $bordered_img;
}

#
# resize_img
#
# Given a path to an image, its new width and height, resizes the image.
# Returns a path to a temporary file where the resized image was stored.
# On error, returns undef.
#
sub resize_img {
    my ($self, $img, $w, $h) = @_;

    my $resized_img = $self->_get_temp_file();
    my @resize_args;
    push @resize_args, "-xsize", $w if $w;
    push @resize_args, "-ysize", $h if $h;
    push @resize_args, $img;

    $self->_run_netpbm_and_redirect("pamscale", @resize_args, $resized_img);
    if ($self->is_error) {
        $self->set_error("Failed resizing '$img': " . $self->get_error);
        return undef;
    }

    return $resized_img;
}

#
# img2pnm
#
# Given a path to an image of almost any type, converts it to a PNM format
# and stores it in a temporary file.
#
# Returns path to the temporary file, or undef on error
#
sub img2pnm {
    my ($self, $infile) = @_;

    my $img_type = $self->get_img_type($infile);
    if ($img_type eq "pnm") { # already pnm!
        return $infile;
    }

    if ($img_type eq "unknown") {
        my $mime = $self->_get_file_mime($infile);
        $self->set_error("Error: can't convert '$mime' ($infile) to pnm!");
    }

    my $tmp_pnm_file = $self->_get_temp_file();
    my $program = "${img_type}topnm";
    $self->_run_netpbm_and_redirect($program, $infile, $tmp_pnm_file);

    return $tmp_pnm_file;
}

#
# pnm2jpeg
#
# Given a path to an image and path to an output image converts input image
# to jpeg format.
# Returns the output path, or undef on failure.
#
sub pnm2jpg {
    my $self = shift;
    my ($pnmfile, $outfile) = @_;

    $self->_run_netpbm_and_redirect("pnmtojpeg", $pnmfile, $outfile);
    if ($self->is_error) {
        $self->{error} = "Failed converting pnm to jpeg ('$pnmfile' to '$outfile'): " . $self->get_error;
        return undef;
    }

    return $outfile;
}


#
# cut_img
#
# Given a path to an image, cuts $w x $h rectangle out of it with top left corner
# at ($x, $y)
# Returns a path to a temporary file where the cut image was stored.
# On error, returns undef;
#
sub cut_img {
    my ($self, $img, $x, $y, $w, $h) = @_;

    my $cut_img = $self->_get_temp_file();
    my @cut_args = (
        "-left",   $x,
        "-top",    $y,
        "-width",  $w,
        "-height", $h,
        $img
    );

    $self->_run_netpbm_and_redirect("pamcut", @cut_args, $cut_img);
    if ($self->is_error) {
        $self->{error} = "Failed cutting '$img': " . $self->get_error;
        return undef;
    }

    return $cut_img;
}

#
# _run_netpbm_and_redirect
#
# Executes a netpbm program with the given arguments and redirects
# the output to a file
#
# =========
# WARNING: non portable, redirects STDERR to /dev/null
# =========
#
sub _run_netpbm_and_redirect {
    my ($self, $program, @args) = @_;
    my $redir = pop @args;

    my $path_to_program = $self->{conf}->{netpbm} . '/' . $program;
    my $full_command = "$path_to_program @args 2>/dev/null > $redir";
    my $ret = system("$path_to_program @args 2>/dev/null > $redir");

    unless ($ret == 0) {    # system() failed
        unlink $redir;
        $self->{error} = "system($full_command) failed: $!";
        return 0;
    }
    return 1;
}

#
# _run_netpbm_and_slurp
#
# Executes a netpbm program with the given arguments and returns the first line
# if run in scalar context or all the lines if run in array context
#
sub _run_netpbm_and_slurp {
    my ($self, $program, @args) = @_;

    my $path_to_program = $self->{conf}->{netpbm} . '/' . $program;
    my $full_command = "$path_to_program @args";
    my $ret = open my $in, '-|', $full_command;
    unless (defined $ret) {
        $self->{error} = "open('-|', $full_command) failed: $!";
        return;
    }

    my $first_line = <$in>;
    return wantarray ? ($first_line, <$in>) : $first_line;
}


# 
# _get_temp_file
#
# Creates and returns the path to a new temporary file
#
sub _get_temp_file {
    return mktemp("/tmp/imageNPXXXXXXXX");
}

sub is_error {
    my $self = shift;
    return 1 if exists $self->{error};
    return 0;
}

sub get_error {
    my $self = shift;
    return $self->{error};
}

sub clear_error {
    my $self = shift;
    delete $self->{error}
}

sub set_error {
    my $self = shift;
    my $error = shift;
    $self->{error} = $error;   
}

1;
