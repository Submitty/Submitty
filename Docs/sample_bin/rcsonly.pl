#!/usr/bin/perl -w

use strict;
use warnings;

$ENV{ 'PATH' } = '/bin:/usr/bin:/usr/sbin:/usr/local/bin';
delete @ENV{'IFS', 'CDPATH', 'ENV', 'BASH_ENV'};

open LIST, "/var/local/hss/instructors/rcslist";	# Should have a list of RCS userids, one per line

while (<LIST>)
{
	chomp $_;
	next if (!$_);  # Skip blank lines
	system ("/usr/sbin/adduser $_ --quiet --home /tmp --gecos \'RCS auth account\' --no-create-home --disabled-password --shell /usr/sbin/nologin");
	print "Done creating $_\n";
}
close (LIST);
