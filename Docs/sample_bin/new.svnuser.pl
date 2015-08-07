#!/usr/bin/perl -w

use strict;
use warnings;

open LIST, "/root/bin/svnlist";	# Should have a list of RCS userids, one per line (make sure you don't have any blank lines in the file)

while (<LIST>)
{
	chomp $_;
	next if (!$_);  # Skip blank lines to avoid making a new repository at the base directory
# create svn repository and set permissions, made clear rather than efficient
	system ("adduser $_ --quiet --home /tmp --gecos \'RCS auth account\' --no-create-home --disabled-password --shell /usr/sbin/nologin");
	system ("svnadmin create /var/lib/svn/course01/$_");
	system ("touch /var/lib/svn/course01/$_/db/rep-cache.db");
	system ("chmod g+w /var/lib/svn/course01/$_/db/rep-cache.db");
	system ("chmod 2770 /var/lib/svn/course01/$_");
	system ("chown -R www-data:svn-course01 /var/lib/svn/course01/$_");
	print "Done creating $_\n";
}
system ("/root/bin/regen.apache");
close (LIST);
