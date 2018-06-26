#!/usr/bin/perl -w

use strict;
use warnings;

$ENV{ 'PATH' } = '/bin:/usr/bin:/usr/sbin:/usr/local/bin';
delete @ENV{'IFS', 'CDPATH', 'ENV', 'BASH_ENV'};

system ("/var/local/submitty/bin/validate.auth.pl");

open LIST, "/var/local/submitty/instructors/authlist";	# Should have a list of userids, one per line

while (<LIST>)
{
	chomp $_;
	next if (!$_);  # Skip blank lines
	system ("/usr/sbin/adduser $_ --quiet --home /tmp --gecos \'AUTH ONLY account\' --no-create-home --disabled-password --shell /usr/sbin/nologin");
	print "Done creating $_\n";
}
close (LIST);
