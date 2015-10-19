#!/usr/bin/perl
use strict;
use warnings;
my $uuid=0;

foreach my $u (0..9) 
{
  $uuid=900+$u;
  system ("addgroup untrusted0$u --gid $uuid");
  system ("adduser untrusted0$u --home /tmp --no-create-home --uid $uuid --gid $uuid --disabled-password --gecos 'untrusted'");
}

foreach my $u (10..59)
{
  $uuid=900+$u;
  system ("addgroup untrusted$u --gid $uuid");
  system ("adduser untrusted$u --home /tmp --no-create-home --uid $uuid --gid $uuid --disabled-password --gecos 'untrusted'");
}

