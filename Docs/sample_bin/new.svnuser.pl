#!/usr/bin/perl -w

use strict;
use warnings;

# Set PATH and remove some environment variables for running in taint mode.
$ENV{ 'PATH' } = '/bin:/usr/bin';
delete @ENV{'IFS', 'CDPATH', 'ENV', 'BASH_ENV'};

        print "Validating user list...\n";
        system ("/usr/local/hss/bin/validate.svn.pl");
        print "Hit Ctrl-C to cancel or Enter to continue.\n";
        getc();

open LIST, "/var/local/hss/instructors/svnlist";	# Should have a list of RCS userids (not email), one per line 

while (<LIST>)
{
	chomp $_;
	next if (!$_);  # Skip blank lines to avoid making a new repository at the base directory
# create svn repository and set permissions, made clear rather than efficient
	system ("/usr/sbin/adduser $_ --quiet --home /tmp --gecos \'RCS auth account\' --no-create-home --disabled-password --shell /usr/sbin/nologin");
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
