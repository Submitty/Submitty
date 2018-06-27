#!/usr/bin/perl -w

use strict;
use warnings;

# Set PATH and remove some environment variables for running in taint mode.
$ENV{ 'PATH' } = '/bin:/usr/bin';
delete @ENV{'IFS', 'CDPATH', 'ENV', 'BASH_ENV'};

system ("/var/local/submitty/bin/validate.svn.pl");

open LIST, "/var/local/submitty/instructors/svnlist";	# Should have a list of userids (not email), one per line 

while (<LIST>)
{
	chomp $_;
	next if (!$_);  # Skip blank lines to avoid making a new repository at the base directory
# create svn repository and set permissions, made clear rather than efficient
	system ("/usr/sbin/adduser $_ --quiet --home /tmp --gecos \'AUTH ONLY account\' --no-create-home --disabled-password --shell /usr/sbin/nologin");
	system ("svnadmin create /var/lib/svn/csci2600/$_");
	system ("touch /var/lib/svn/csci2600/$_/db/rep-cache.db");
	system ("chmod g+w /var/lib/svn/csci2600/$_/db/rep-cache.db");
	system ("chmod 2770 /var/lib/svn/csci2600/$_");
	system ("chown -R www-data:svn-csci2600 /var/lib/svn/csci2600/$_");
	system ("ln -s /var/lib/svn/hooks/pre-commit /var/lib/svn/csci2600/$_/hooks/pre-commit");
	print "Done creating $_\n";
}
system ("/root/bin/regen.apache");
system ("/usr/sbin/apache2ctl -t");
close (LIST);
