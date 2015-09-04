#!/usr/bin/perl -w

use strict;
use warnings;

$ENV{ 'PATH' } = '/bin:/usr/bin:/usr/sbin:/usr/local/bin';
delete @ENV{'IFS', 'CDPATH', 'ENV', 'BASH_ENV'};

open LIST, "/var/local/hss/instructors/svnlist" or die "file svnlist not found";	# Should have a list of RCS userids to be enabled, one per line

my $GOOD = "/var/local/hss/instructors/valid";

while (<LIST>)
{
	chomp $_;
	next if (!$_);  # Skip blank lines
	if (system ("grep $_ $GOOD > /dev/null")) {
		print "*ERROR*: $_ is not a valid userid.\n";
	} else {
	if (!(system ("id $_ > /dev/null 2>&1"))) {
		print "$_ already exists.\n";
		}
	}
}
close (LIST);
