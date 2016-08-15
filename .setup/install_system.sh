#!/usr/bin/env bash

# TIMEZONE
timedatectl set-timezone America/New_York

#PATHS
SUBMITTY_REPOSITORY=/usr/local/submitty/GIT_CHECKOUT_Submitty
SUBMITTY_INSTALL_DIR=/usr/local/submitty
SUBMITTY_DATA_DIR=/var/local/submitty

COURSE_BUILDERS_GROUP=course_builders

#################################################################
# PROVISION SETUP
#################
if [[ $1 == vagrant ]]; then
  echo "Non-interactive vagrant script..."
  VAGRANT=1
  export DEBIAN_FRONTEND=noninteractive
else
  #TODO: We should get options for ./.setup/CONFIGURE_SUBMITTY.sh script
  VAGRANT=0
fi

#################################################################
# UBUNTU SETUP
#################
if [ ${VAGRANT} == 1 ]; then
    chmod -x /etc/update-motd.d/*
    chmod -x /usr/share/landscape/landscape-sysinfo.wrapper
    chmod +x /etc/update-motd.d/00-header

    echo -e '
  _______  __   __  _______  __   __  ___   _______  _______  __   __
|       ||  | |  ||  _    ||  |_|  ||   | |       ||       ||  | |  |
|  _____||  | |  || |_|   ||       ||   | |_     _||_     _||  |_|  |
| |_____ |  |_|  ||       ||       ||   |   |   |    |   |  |       |
|_____  ||       ||  _   | |       ||   |   |   |    |   |  |_     _|
 _____| ||       || |_|   || ||_|| ||   |   |   |    |   |    |   |
|_______||_______||_______||_|   |_||___|   |___|    |___|    |___|

############################################################
##  All user accounts have same password unless otherwise ##
##  noted below. The following user accounts exist:       ##
##    vagrant/vagrant, root/vagrant, hsdbu, hwphp,        ##
##    hwcgi hwcron, ta, instructor, developer,            ##
##    postgres                                            ##
##                                                        ##
##  The following accounts have database accounts         ##
##  with same password as above:                          ##
##    hsdbu, postgres, root, vagrant                      ##
##                                                        ##
##  The VM can be accessed with the following urls:       ##
##    https://192.168.56.101 (submission)                 ##
##    https://192.168.56.102 (cgi-bin scripts)            ##
##    https://192.168.56.103 (svn)                        ##
##    https://192.168.56.104 (tagrading)                  ##
##                                                        ##
##  The database can be accessed on the host machine at   ##
##   localhost:15432                                      ##
##                                                        ##
##  Happy developing!                                     ##
############################################################
' > /etc/motd
    chmod +rx /etc/motd

    echo "192.168.56.101    test-submit test-submit.cs.rpi.edu" >> /etc/hosts
    echo "192.168.56.102    test-cgi test-cgi.cs.rpi.edu" >> /etc/hosts
    echo "192.168.56.103    test-svn test-svn.cs.rpi.edu" >> /etc/hosts
    echo "192.168.56.104    test-hwgrading test-hwgrading.cs.rpi.edu" >> /etc/hosts
fi



#################################################################
# PACKAGE SETUP
#################
echo "\n" | add-apt-repository ppa:webupd8team/java
apt-get -qq update


############################
# NTP: Network Time Protocol
# You want to be sure the clock stays in sync, especially if you have
# deadlines for homework to be submitted.
#
# The default settings are ok, but you can edit /etc/ntp.conf and
# replace the default servers with your local ntp server to reduce
# traffic through your campus uplink (To replace the default servers
# with your own, comment out the default servers by adding a # before
# the lines that begin with “server” and add your server under the
# line that says “Specify one or more NTP servers” with something
# along the lines of “server xxx.xxx.xxx.xxx”)

apt-get install -qqy ntp
service ntp restart

# path for untrusted user creation script will be different if not using Vagrant
${SUBMITTY_REPOSITORY}/.setup/create.untrusted.users.pl

apt-get install -qqy libpam-passwdqc


# Use suphp to improve file permission granularity by running php
# scripts as the user that owns the file instead of www-data
#
# Set up apache to run with suphp in pre-fork mode since not all
# modules are thread safe (do not combine the commands or you may get
# the worker/threaded mode instead)

apt-get install -qqy ssh sshpass unzip
apt-get install -qqy apache2 postgresql postgresql-contrib php5 php5-xdebug libapache2-mod-suphp php5-curl

# Check to make sure you got the right setup by typing:
#   apache2ctl -V | grep MPM
# (it should say prefork)

apachectl -V | grep MPM

# Add additional packages for compiling, authentication, and security,
# and program support

# DOCUMENTATION FIXME: Go through this list and categorize purpose of
# these packages (as appropriate.. )

echo "Preparing to install packages.  This may take a while."
apt-get install -qqy clang autoconf automake autotools-dev clisp diffstat emacs finger gdb git git-man \
hardening-includes python python-pip p7zip-full patchutils postgresql-client postgresql-client-9.3 postgresql-client-common \
unzip valgrind zip libmagic-ocaml-dev common-lisp-controller libboost-all-dev javascript-common \
apache2-suexec-custom libapache2-mod-authnz-external libapache2-mod-authz-unixgroup libfile-mmagic-perl \
libgnupg-interface-perl php5-pgsql php5-mcrypt libbsd-resource-perl libarchive-zip-perl gcc g++ g++-multilib jq libseccomp-dev \
libseccomp2 seccomp junit cmake libpcre3 libpcre3-dev flex bison

apt-get install -qqy subversion subversion-tools
apt-get install -qqy libapache2-svn

# Enable PHP5-mcrypt
php5enmod mcrypt

# Install Oracle 8 Non-Interactively
echo oracle-java8-installer shared/accepted-oracle-license-v1-1 select true | sudo /usr/bin/debconf-set-selections
apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys EEA14886
echo "installing java8"
apt-get install -qqy oracle-java8-installer > /dev/null 2>&1

# Install Racket and Swi-prolog for Programming Languages
echo "installing Racket and Swi-prolog"
apt-add-repository -y ppa:plt/racket  > /dev/null 2>&1
apt-get install -qqy racket > /dev/null 2>&1
apt-get install -qqy swi-prolog > /dev/null 2>&1

# Install Image Magick for image comparison, etc.
apt-get install -qqy imagemagick

apt-get -qqy autoremove


# TODO: We should look into making it so that only certain users have access to certain packages
# so that hwphp is the only one who could use PAM for example
pip install python-pam
pip install xlsx2csv
#NOTE: BELOW THE PYTHON PAM MODULE IS RESTRICTED TO hwcgi

chmod -R 555 /usr/local/lib/python2.7/*
chmod 555 /usr/lib/python2.7/dist-packages


#################################################################
# JAR SETUP
#################
echo "Getting JUnit..."
mkdir -p ${SUBMITTY_INSTALL_DIR}/JUnit
chown root:${COURSE_BUILDERS_GROUP} ${SUBMITTY_INSTALL_DIR}/JUnit
chmod 750 ${SUBMITTY_INSTALL_DIR}/JUnit
cd ${SUBMITTY_INSTALL_DIR}/JUnit

wget http://search.maven.org/remotecontent?filepath=junit/junit/4.12/junit-4.12.jar -o /dev/null > /dev/null 2>&1
mv remotecontent?filepath=junit%2Fjunit%2F4.12%2Fjunit-4.12.jar junit-4.12.jar
wget http://search.maven.org/remotecontent?filepath=org/hamcrest/hamcrest-core/1.3/hamcrest-core-1.3.jar -o /dev/null > /dev/null 2>&1
mv remotecontent?filepath=org%2Fhamcrest%2Fhamcrest-core%2F1.3%2Fhamcrest-core-1.3.jar hamcrest-core-1.3.jar


# EMMA is a tool for computing code coverage of Java programs

echo "Getting emma..."
wget http://downloads.sourceforge.net/project/emma/emma-release/2.0.5312/emma-2.0.5312.zip -o /dev/null > /dev/null 2>&1
unzip emma-2.0.5312.zip > /dev/null
mv emma-2.0.5312/lib/emma.jar emma.jar
rm -rf emma-2.0.5312
rm emma-2.0.5312.zip
rm index.html* > /dev/null 2>&1

chmod o+r . *.jar

#################################################################
# DRMEMORY SETUP
#################

# Dr Memory is a tool for detecting memory errors in C++ programs (similar to Valgrind)

echo "Getting DrMemory..."
mkdir -p ${SUBMITTY_INSTALL_DIR}/DrMemory
cd ${SUBMITTY_INSTALL_DIR}/DrMemory
DRMEM_TAG=release_1.10.1
DRMEM_VER=1.10.1-3
wget https://github.com/DynamoRIO/drmemory/releases/download/${DRMEM_TAG}/DrMemory-Linux-${DRMEM_VER}.tar.gz -o /dev/null > /dev/null 2>&1
tar -xpzf DrMemory-Linux-${DRMEM_VER}.tar.gz -C ${SUBMITTY_INSTALL_DIR}/DrMemory
ln -s ${SUBMITTY_INSTALL_DIR}/DrMemory/DrMemory-Linux-${DRMEM_VER} ${SUBMITTY_INSTALL_DIR}/drmemory
rm DrMemory-Linux-${DRMEM_VER}.tar.gz
chown -R root:${COURSE_BUILDERS_GROUP} ${SUBMITTY_INSTALL_DIR}/DrMemory
# FIXME: these permissions could probably be adjusted
chmod -R 755 ${SUBMITTY_INSTALL_DIR}/DrMemory

#################################################################
# NETWORK CONFIGURATION
#################
if [ ${VAGRANT} == 1 ]; then
    #
    # The goal here is to ensure the VM is accessible from your own
    # computer for code testing, has an outgoing connection to the
    # Internet to access github and receive Ubuntu updates, but is also
    # unreachable via incoming Internet connections so to block uninvited
    # guests.
    #
    # Open the VM’s Settings window and click on the “Network” tab.  There
    # are tabs for four network adapters.  Enable adapters #1 and #2.
    #
    # Adapter #1 should be attached to NAT and make sure the cable
    # connected box (under advanced) is checked.  You may ignore all other
    # settings for adapter #1.  This provides the VM’s outgoing Internet
    # connection, but uninvited guests on the Internet cannot see the VM.
    #
    # Adapter #2 should be attached to Host-only Network.  Under “name”,
    # there is a drop down menu to select Host-only Ethernet Adapter (or
    # vboxnet).  Recall that this was created in the previous section,
    # Create Virtual Network Adapters.  This adapter can only communicate
    # between your host OS and the VM, and it is unreachable to and from
    # the Internet.
    #
    # After Ubuntu is fully installed, you need to adjust the networking
    # configuration so that you may access the VM via static IP addressing
    # as a convenience for code testing.
    #
    # The VM’s host-only adapter provides a private connection to the VM,
    # but Ubuntu also needs to be configured to use this adapter.

    echo "Binding static IPs to \"Host-Only\" virtual network interface."

    # eth0 is auto-configured by Vagrant as NAT.  eth1 is a host-only adapter and
    # not auto-configured.  eth1 is manually set so that the host-only network
    # interface remains consistent among VM reboots as Vagrant has a bad habit of
    # discarding and recreating networking interfaces everytime the VM is restarted.
    # eth1 is statically bound to 192.168.56.101, 102, 103, 104, and 105.
    printf "auto eth1\niface eth1 inet static\naddress 192.168.56.101\nnetmask 255.255.255.0\n\n" >> /etc/network/interfaces.d/eth1.cfg
    printf "auto eth1:1\niface eth1:1 inet static\naddress 192.168.56.102\nnetmask 255.255.255.0\n\n" >> /etc/network/interfaces.d/eth1.cfg
    printf "auto eth1:2\niface eth1:2 inet static\naddress 192.168.56.103\nnetmask 255.255.255.0\n\n" >> /etc/network/interfaces.d/eth1.cfg
    printf "auto eth1:3\niface eth1:3 inet static\naddress 192.168.56.104\nnetmask 255.255.255.0\n" >> /etc/network/interfaces.d/eth1.cfg

    # Turn them on.
    ifup eth1 eth1:1 eth1:2 eth1:3 eth1:4
fi


#################################################################
# APACHE SETUP
#################
a2enmod include actions cgi suexec authnz_external headers ssl


# If you have real certificates, follow the directions from your
# certificate provider.
#
# If you are just developing and do not have real ssl certificates,
# follow these directions for creating a self-signed (aka “snakeoil
# certificate”)
#
# If it doesn’t already exist, create directory path
#   /etc/apache2/ssl/
#
# An expiration of 365000 days (roughly 1000 years) is meant so that
# the certificate essentially never expires.  make the certificates
# world readable (but not the key):

mkdir /etc/apache2/ssl
cd /etc/apache2/ssl
echo "creating ssl certificates"

echo -e "US
New York
Troy
RPI
CSCI
.
." | openssl req -x509 -nodes -days 365000 -newkey rsa:2048 -keyout svn.key -out svn.crt > /dev/null 2>&1

chmod o+r svn.crt

echo -e "#%PAM-1.0
auth required pam_unix.so
account required pam_unix.so" > /etc/pam.d/httpd

if [ ${VAGRANT} == 1 ]; then
    # Loosen password requirements on vagrant box
	sed -i '25s/^/\#/' /etc/pam.d/common-password
	sed -i '26s/pam_unix.so obscure use_authtok try_first_pass sha512/pam_unix.so obscure minlen=1 sha512/' /etc/pam.d/common-password
    # Set the ServerName
	# echo -e "\nServerName 10.0.2.15\n" >> /etc/apache2/apache2.conf
fi


# comment out directory configs - should be converted to something more flexible
sed -i '153,174s/^/#/g' /etc/apache2/apache2.conf

# remove default sites which would cause server to mess up
rm /etc/apache2/sites*/000-default.conf
rm /etc/apache2/sites*/default-ssl.conf

service apache2 reload


#################################################################
# PHP SETUP
#################
sed -i -e 's/^docroot=/docroot=\/usr\/local\/submitty:/g' /etc/suphp/suphp.conf

# Assumes you need to have a group of people able to edit the files.  Comment out if not needed
sed -i -e 's/^allow_file_group_writeable=false/allow_file_group_writeable=true/g' /etc/suphp/suphp.conf
# Assumes you need to have a group of people able to add/delete files and directories.  Comment out if not needed.
sed -i -e 's/^allow_directory_group_writeable=false/allow_directory_group_writeable=true/g' /etc/suphp/suphp.conf
# do not allow others_writable files or directories or you will have even less security than without suphp

# Edit php settings.  Note that if you need to accept larger files,
# you’ll need to increase both upload_max_filesize and
# post_max_filesize

sed -i -e 's/^max_execution_time = 30/max_execution_time = 60/g' /etc/php5/cgi/php.ini
sed -i -e 's/^upload_max_filesize = 2M/upload_max_filesize = 10M/g' /etc/php5/cgi/php.ini
sed -i -e 's/^session.gc_maxlifetime = 1440/session.gc_maxlifetime = 86400/' /etc/php5/cgi/php.ini
sed -i -e 's/^session.gc_maxlifetime = 1440/session.gc_maxlifetime = 86400/' /etc/php5/apache2/php.ini
sed -i -e 's/^post_max_size = 8M/post_max_size = 10M/g' /etc/php5/cgi/php.ini
sed -i -e 's/^allow_url_fopen = On/allow_url_fopen = Off/g' /etc/php5/cgi/php.ini
sed -i -e 's/^session.cookie_httponly =/session.cookie_httponly = 1/g' /etc/php5/cgi/php.ini


#################################################################
# USERS SETUP
#################

# Special users and groups needed to run Submitty
#
# It is probably easiest to set and store passwords for the special
# accounts, although you can also use ‘sudo su user’ to change to the
# desired user on the local machine which works for most things.

# The group hwcronphp allows hwphp to write the submissions, but give
# read-only access to the hwcron user.  And the hwcron user writes the
# results, and gives read-only access to the hwphp user.

addgroup hwcronphp

# The group course_builders allows instructors/head TAs/course
# managers to write website custimization files and run course
# management scripts.

addgroup course_builders

if [ ${VAGRANT} == 1 ]; then
	adduser vagrant sudo
	adduser ta --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
	echo "ta:ta" | sudo chpasswd
	adduser instructor --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
	echo "instructor:instructor" | sudo chpasswd
	adduser instructor sudo
	adduser developer --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
	echo "developer:developer" | sudo chpasswd
	adduser developer sudo
fi


# change the default user umask (was 002)
sed -i  "s/^UMASK.*/UMASK 027/g"  /etc/login.defs
grep -q "^UMASK 027" /etc/login.defs || (echo "ERROR! failed to set umask" && exit)


adduser hwphp --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
adduser hwcgi --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
adduser hwcgi hwphp
adduser hwcgi shadow
if [ ${VAGRANT} == 1 ]; then
	echo "hwphp:hwphp" | sudo chpasswd
	echo "hwcgi:hwcgi" | sudo chpasswd
	adduser hwphp vagrant
	adduser hwcgi vagrant
fi
adduser hwcron --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
if [ ${VAGRANT} == 1 ]; then
	echo "hwcron:hwcron" | sudo chpasswd
fi

# only hwcgi can use the python pam module
chown hwcgi:hwcgi /usr/local/lib/python2.7/dist-packages/pam*
chmod 500         /usr/local/lib/python2.7/dist-packages/pam*


# FIXME:  umask setting above not complete
# might need to also set USERGROUPS_ENAB to "no", and manually create
# the hwphp and hwcron single user groups.  See also /etc/login.defs
echo -e "\n# set by the .setup/install_system.sh script\numask 027" >> /home/hwphp/.profile
echo -e "\n# set by the .setup/install_system.sh script\numask 027" >> /home/hwcgi/.profile
echo -e "\n# set by the .setup/install_system.sh script\numask 027" >> /home/hwcron/.profile


adduser hsdbu --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
if [ ${VAGRANT} == 1 ]; then
	echo "hsdbu:hsdbu" | sudo chpasswd
fi
adduser hwphp hwcronphp
adduser hwcron hwcronphp

for COURSE in csci1000 csci1100 csci1200 csci2600
do

    # for each course, create a group to contain the current
    # instructor along the lines of:

	addgroup $COURSE

    # and another group to contain the current instructor, TAs,
    # hwcron, and hwphp along the lines of

	addgroup $COURSE\_tas_www
	adduser hwphp $COURSE\_tas_www
	adduser hwcron $COURSE\_tas_www
	if [ ${VAGRANT} == 1 ]; then
		adduser ta $COURSE
		adduser instructor $COURSE
		adduser	instructor $COURSE\_tas_www
		adduser developer $COURSE
		adduser developer $COURSE\_tas_www
	fi
done

if [ ${VAGRANT} == 1 ]; then
	adduser instructor course_builders
fi


# create directories and fix permissions

mkdir -p ${SUBMITTY_DATA_DIR}


# create a list of valid userids and put them in /var/local/submitty/instructors
# one way to create your list is by listing all of the userids in /home

mkdir -p ${SUBMITTY_DATA_DIR}/instructors
ls /home | sort > ${SUBMITTY_DATA_DIR}/instructors/valid


#################################################################
# SVN SETUP
#################
a2enmod dav
a2enmod dav_fs
a2enmod authz_svn
a2enmod authz_groupfile

# Choose a directory for holding your subversion files that will get
# backed up if it is a production server.  We use /var/lib/svn by
# default.
mkdir -p /var/lib/svn
chmod g+s /var/lib/svn

# make a group and subdirectory for any classes requiring subversion
# repositories:
mkdir -p /var/lib/svn/csci2600
touch /var/lib/svn/svngroups
chown www-data:csci2600_tas_www /var/lib/svn/csci2600 /var/lib/svn/svngroups
if [ ${VAGRANT} == 1 ]; then
    # set up ssh keys for hwcron to connect to the subversion
    # repository (do not use root/sudo except as shown)
	su hwcron
        # generate the key (accept the defaults):
	echo -e "\n" | ssh-keygen -t rsa -b 4096 -N "" > /dev/null 2>&1
	echo "hwcron" > password.txt
        # copy the key to test-svn:
	sshpass -f password.txt ssh-copy-id hwcron@test-svn
	rm password.txt
	echo "csci2600_tas_www: hwcron ta instructor developer" >> /var/lib/svn/svngroups
fi

#################################################################
# POSTGRES SETUP
#################
if [ ${VAGRANT} == 1 ]; then
	echo "postgres:postgres" | chpasswd postgres
	adduser postgres shadow
	service postgresql restart
	PG_VERSION="$(psql -V | egrep -o '[0-9]{1,}.[0-9]{1,}')"
	sed -i -e "s/# ----------------------------------/# ----------------------------------\nhostssl    all    all    192.168.56.0\/24    pam\nhost       all    all    192.168.56.0\/24    pam\nhost       all    all    all                md5/" /etc/postgresql/${PG_VERSION}/main/pg_hba.conf
	echo "Creating PostgreSQL users"
	su postgres -c "source ${SUBMITTY_REPOSITORY}/.setup/vagrant/db_users.sh";
	echo "Finished creating PostgreSQL users"

	sed -i "s/#listen_addresses = 'localhost'/listen_addresses = '*'/" "/etc/postgresql/${PG_VERSION}/main/postgresql.conf"
	service postgresql restart
fi

#################################################################
# ANALYSIS TOOLS SETUP
#################

git clone 'https://github.com/Submitty/AnalysisTools' ${SUBMITTY_INSTALL_DIR}/GIT_CHECKOUT_AnalysisTools
#pushd ${SUBMITTY_INSTALL_DIR}/GIT_CHECKOUT_AnalysisTools
#make ubuntudeps
# graph tool...  for later?  add-apt-repository "http://downloads.skewed.de/apt/trusty universe" -y
add-apt-repository ppa:ubuntu-toolchain-r/test -y
apt-get update -qq
apt-get install -qq build-essential pkg-config flex bison
apt-get install -qq libpcre3 libpcre3-dev
apt-get install -qq splint indent
apt-get install -qq python3 python3-dev libpython3.4 python3-pip
python3 -m pip install pylint
# graph tool...  for later?  apt-get install -qq --force-yes python3-graph-tool
#popd


#################################################################
# SUBMITTY SETUP
#################

if [ ${VAGRANT} == 1 ]; then
	echo -e "localhost
hsdbu
hsdbu
http://192.168.56.101
http://192.168.56.104
http://192.168.56.102
svn+ssh:192.168.56.103" | source ${SUBMITTY_REPOSITORY}/.setup/CONFIGURE_SUBMITTY.sh
else
	source ${SUBMITTY_REPOSITORY}/.setup/CONFIGURE_SUBMITTY.sh
fi

source ${SUBMITTY_INSTALL_DIR}/.setup/INSTALL_SUBMITTY.sh clean
#source ${SUBMITTY_INSTALL_DIR}/.setup/INSTALL_SUBMITTY.sh clean test

source ${SUBMITTY_REPOSITORY}/Docs/sample_bin/admin_scripts_setup
cp ${SUBMITTY_REPOSITORY}/.setup/vagrant/hwgrading.conf /etc/apache2/sites-available/hwgrading.conf
cp ${SUBMITTY_REPOSITORY}/.setup/vagrant/submitty.conf /etc/apache2/sites-available/submitty.conf
cp ${SUBMITTY_REPOSITORY}/.setup/vagrant/cgi.conf /etc/apache2/sites-available/cgi.conf
cp ${SUBMITTY_REPOSITORY}/.setup/vagrant/www-data /etc/apache2/suexec/www-data

# permissions: rw- r-- ---
chmod 0640 /etc/apache2/sites-available/*.conf
chmod 0640 /etc/apache2/suexec/www-data

if [ ${VAGRANT} == 1 ]; then
	sed -i 's/SSLCertificateChainFile/#SSLCertificateChainFile/g' /root/bin/bottom.txt
	sed -i 's/course01/csci2600/g' /root/bin/gen.middle
fi

a2ensite hwgrading
a2ensite submitty
a2ensite cgi

apache2ctl -t
service apache2 restart

if [[ ${VAGRANT} == 1 ]]; then
    echo "student" >> ${SUBMITTY_DATA_DIR}/instructors/authlist
    echo "student" >> ${SUBMITTY_DATA_DIR}/instructors/valid
    echo "smithj" >> ${SUBMITTY_DATA_DIR}/instructors/authlist
    echo "smithj" >> ${SUBMITTY_DATA_DIR}/instructors/valid
    ${SUBMITTY_DATA_DIR}/bin/authonly.pl
    echo "student:student" | sudo chpasswd
    echo "smithj:smithj" | sudo chpasswd

    rm -r ${SUBMITTY_DATA_DIR}/autograding_logs
    rm -r ${SUBMITTY_REPOSITORY}/.vagrant/autograding_logs
    mkdir ${SUBMITTY_REPOSITORY}/.vagrant/autograding_logs
    ln -s ${SUBMITTY_REPOSITORY}/.vagrant/autograding_logs ${SUBMITTY_DATA_DIR}/autograding_logs
    rm -r ${SUBMITTY_DATA_DIR}/tagrading_logs
    rm -r ${SUBMITTY_REPOSITORY}/.vagrant/tagrading_logs
    mkdir ${SUBMITTY_REPOSITORY}/.vagrant/tagrading_logs
    ln -s ${SUBMITTY_REPOSITORY}/.vagrant/tagrading_logs ${SUBMITTY_DATA_DIR}/tagrading_logs

    # Call helper script that makes the courses and refreshes the database
    chmod u+x ${SUBMITTY_REPOSITORY}/.setup/add_sample_courses.py
    ${SUBMITTY_REPOSITORY}/.setup/add_sample_courses.py

    #################################################################
    # SET CSV FIELDS (for classlist upload data)
    #################

	# Vagrant auto-settings are based on Rensselaer Polytechnic Institute School
	# of Science 2015-2016.

	# Other Universities will need to rerun /bin/setcsvfields to match their
	# classlist csv data.  See wiki for details.
	${SUBMITTY_INSTALL_DIR}/bin/setcsvfields 13 12 15 7
fi

# Deferred ownership change
chown hwphp:hwphp ${SUBMITTY_INSTALL_DIR}

# With this line, subdirectories inherit the group by default and
# blocks r/w access to the directory by others on the system.
chmod 2771 ${SUBMITTY_INSTALL_DIR}

echo "Done."
exit 0
