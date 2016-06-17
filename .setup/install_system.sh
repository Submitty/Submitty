#!/usr/bin/env bash

#PATHS
SUBMITTY_DIR=/usr/local/hss/GIT_CHECKOUT_Submitty
INSTALL_DIR=/usr/local/hss
DATA_DIR=/var/local/hss

#################################################################
# PROVISION SETUP
#################
if [[ $1 == vagrant ]]; then
  echo "Non-interactive vagrant script..."
  VAGRANT=1
  export DEBIAN_FRONTEND=noninteractive
else
  #TODO: We should get options for ./CONFIGURE script
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
 __   __  _     _  _______  _______  ______    __   __  _______  ______
|  | |  || | _ | ||       ||       ||    _ |  |  | |  ||       ||    _ |
|  |_|  || || || ||  _____||    ___||   | ||  |  |_|  ||    ___||   | ||
|       ||       || |_____ |   |___ |   |_||_ |       ||   |___ |   |_||_
|       ||       ||_____  ||    ___||    __  ||       ||    ___||    __  |
|   _   ||   _   | _____| ||   |___ |   |  | | |     | |   |___ |   |  | |
|__| |__||__| |__||_______||_______||___|  |_|  |___|  |_______||___|  |_|

############################################################
##  All user accounts have same password unless otherwise ##
##  noted below. The following user accounts exist:       ##
##    vagrant/vagrant, root/vagrant, hsdbu, hwphp         ##
##    hwcron, ta, instructor, developer, postgres         ##
##                                                        ##
##  The following accounts have database accounts         ##
##  with same password as above:                          ##
##    hsdbu, postgres, root, vagrant                      ##
##                                                        ##
##  The VM can be accessed with the following urls:       ##
##    https://192.168.56.101 (submission)                 ##
##    https://192.168.56.102 (svn)                        ##
##    https://192.168.56.103 (grading)                    ##
##                                                        ##
##  The database can be accessed via localhost:15432      ##
##                                                        ##
##  Happy developing!                                     ##
############################################################
' > /etc/motd
    chmod +rx /etc/motd

    echo "192.168.56.101    test-submit test-submit.cs.rpi.edu" >> /etc/hosts
    echo "192.168.56.102    test-svn test-svn.cs.rpi.edu" >> /etc/hosts
    echo "192.168.56.103    test-hwgrading test-hwgrading.cs.rpi.edu hwgrading" >> /etc/hosts
fi

#################################################################
# PACKAGE SETUP
#################
echo "\n" | add-apt-repository ppa:webupd8team/java
apt-get -qq update

apt-get install -qqy ntp
service ntp restart

# path for untrusted user creation script will be different if not using Vagrant
${SUBMITTY_DIR}/bin/create.untrusted.users.pl

apt-get install -qqy libpam-passwdqc

apt-get install -qqy ssh sshpass unzip
apt-get install -qqy apache2 postgresql postgresql-contrib php5 php5-xdebug libapache2-mod-suphp

apachectl -V | grep MPM

echo "Preparing to install packages.  This may take a while."
apt-get install -qqy clang autoconf automake autotools-dev clisp diffstat emacs finger gdb git git-man \
hardening-includes python python-pip p7zip-full patchutils postgresql-client postgresql-client-9.3 postgresql-client-common \
unzip valgrind zip libmagic-ocaml-dev common-lisp-controller libboost-all-dev javascript-common \
apache2-suexec-custom libapache2-mod-authnz-external libapache2-mod-authz-unixgroup libfile-mmagic-perl \
libgnupg-interface-perl php5-pgsql php5-mcrypt libbsd-resource-perl libarchive-zip-perl gcc g++ g++-multilib jq libseccomp-dev \
libseccomp2 seccomp junit cmake xlsx2csv libpcre3 libpcre3-dev flex bison

pip install python-pam
chmod 555 /usr/local/lib/python2.7/*
chmod 555 /usr/lib/python2.7/dist-packages

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
apt-get install imagemagick

#################################################################
# NETWORK CONFIGURATION
#################

echo "Binding static IPs to \"Host-Only\" virtual network interface."

# eth0 is auto-configured by Vagrant as NAT.  eth1 is a host-only adapter and
# not auto-configured.  eth1 is manually set so that the host-only network
# interface remains consistent among VM reboots as Vagrant has a bad habit of
# discarding and recreating networking interfaces everytime the VM is restarted.
# eth1 is statically bound to 192.168.56.101, 102, and 103.
printf "auto eth1\niface eth1 inet static\naddress 192.168.56.101\nnetmask 255.255.255.0\n\n" >> /etc/network/interfaces.d/eth1.cfg
printf "auto eth1:1\niface eth1:1 inet static\naddress 192.168.56.102\nnetmask 255.255.255.0\n\n" >> /etc/network/interfaces.d/eth1.cfg
printf "auto eth1:2\niface eth1:2 inet static\naddress 192.168.56.103\nnetmask 255.255.255.0\n" >> /etc/network/interfaces.d/eth1.cfg

# Turn them on.
ifup eth1 eth1:1 eth1:2

#################################################################
# JAR SETUP
#################
echo "Getting JUnit..."
mkdir -p ${INSTALL_DIR}/JUnit
cd ${INSTALL_DIR}/JUnit

wget http://search.maven.org/remotecontent?filepath=junit/junit/4.12/junit-4.12.jar -o /dev/null > /dev/null 2>&1
mv remotecontent?filepath=junit%2Fjunit%2F4.12%2Fjunit-4.12.jar junit-4.12.jar
wget http://search.maven.org/remotecontent?filepath=org/hamcrest/hamcrest-core/1.3/hamcrest-core-1.3.jar -o /dev/null > /dev/null 2>&1
mv remotecontent?filepath=org%2Fhamcrest%2Fhamcrest-core%2F1.3%2Fhamcrest-core-1.3.jar hamcrest-core-1.3.jar

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
echo "Getting DrMemory..."
mkdir -p ${INSTALL_DIR}/DrMemory
cd /tmp
DRMEM_TAG=release_1.10.1
DRMEM_VER=1.10.1-3
wget https://github.com/DynamoRIO/drmemory/releases/download/${DRMEM_TAG}/DrMemory-Linux-${DRMEM_VER}.tar.gz -o /dev/null > /dev/null 2>&1
tar -xpzf DrMemory-Linux-${DRMEM_VER}.tar.gz -C ${INSTALL_DIR}/DrMemory
ln -s ${INSTALL_DIR}/DrMemory/DrMemory-Linux-${DRMEM_VER} ${INSTALL_DIR}/drmemory
rm DrMemory-Linux-${DRMEM_VER}.tar.gz

#################################################################
# APACHE SETUP
#################
a2enmod include actions cgi suexec authnz_external headers ssl

mkdir /etc/apache2/ssl
cd /etc/apache2/ssl
echo "creating ssl certificates"
echo -e "US
New York
Troy
RPI
CSCI
.
." | openssl req -x509 -nodes -days 365000 -newkey rsa:2048 -keyout submit.key -out submit.crt > /dev/null 2>&1

echo -e "US
New York
Troy
RPI
CSCI
.
." | openssl req -x509 -nodes -days 365000 -newkey rsa:2048 -keyout hwgrading.key -out hwgrading.crt > /dev/null 2>&1

echo -e "US
New York
Troy
RPI
CSCI
.
." | openssl req -x509 -nodes -days 365000 -newkey rsa:2048 -keyout svn.key -out svn.crt > /dev/null 2>&1

chmod o+r hwgrading.crt
chmod o+r submit.crt
chmod o+r svn.crt

echo -e "#%PAM-1.0
auth required pam_unix.so
account required pam_unix.so" > /etc/pam.d/httpd

if [ ${VAGRANT} == 1 ]; then
    # Loosen password requirements on vagrant box
	sed -i '25s/^/\#/' /etc/pam.d/common-password
	sed -i '26s/pam_unix.so obscure use_authtok try_first_pass sha512/pam_unix.so obscure minlen=1 sha512/' /etc/pam.d/common-password
    # Set the ServerName
	echo -e "\nServerName 10.0.2.15\n" >> /etc/apache2/apache2.conf
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
sed -i -e 's/^docroot=/docroot=\/usr\/local\/hss:/g' /etc/suphp/suphp.conf
sed -i -e 's/^allow_file_group_writeable=false/allow_file_group_writeable=true/g' /etc/suphp/suphp.conf
sed -i -e 's/^allow_directory_group_writeable=false/allow_directory_group_writeable=true/g' /etc/suphp/suphp.conf
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
addgroup hwcronphp
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
if [ ${VAGRANT} == 1 ]; then
	echo "hwphp:hwphp" | sudo chpasswd
	adduser hwphp vagrant
	# FIXME: We should figure out what the method for PAM authentication is
	adduser hwphp shadow
fi
adduser hwcron --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
if [ ${VAGRANT} == 1 ]; then
	echo "hwcron:hwcron" | sudo chpasswd
fi


# FIXME:  umask setting above not complete
# might need to also set USERGROUPS_ENAB to "no", and manually create
# the hwphp and hwcron single user groups.  See also /etc/login.defs
echo -e "\n# set by the .setup/install_system.sh script\numask 027" >> /home/hwphp/.profile
echo -e "\n# set by the .setup/install_system.sh script\numask 027" >> /home/hwcron/.profile


adduser hsdbu --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
if [ ${VAGRANT} == 1 ]; then
	echo "hsdbu:hsdbu" | sudo chpasswd
fi
adduser hwphp hwcronphp
adduser hwcron hwcronphp

for COURSE in csci1100 csci1200 csci2600
do
	addgroup $COURSE
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

mkdir -p ${DATA_DIR}

mkdir -p ${DATA_DIR}/instructors
ls /home | sort > ${DATA_DIR}/instructors/valid

#################################################################
# SVN SETUP
#################
apt-get install -qqy subversion subversion-tools
apt-get install -qqy libapache2-svn
a2enmod dav
a2enmod dav_fs
a2enmod authz_svn
a2enmod authz_groupfile

mkdir -p /var/lib/svn
chmod g+s /var/lib/svn

mkdir -p /var/lib/svn/csci2600
touch /var/lib/svn/svngroups
chown www-data:csci2600_tas_www /var/lib/svn/csci2600 /var/lib/svn/svngroups
if [ ${VAGRANT} == 1 ]; then
	su hwcron
	echo -e "\n" | ssh-keygen -t rsa -b 4096 -N "" > /dev/null 2>&1
	echo "hwcron" > password.txt
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
	sed -i -e "s/# ----------------------------------/# ----------------------------------\nhostssl    all    all    192.168.56.0\/24    pam\nhost    all    all    192.168.56.0\/24    pam/" /etc/postgresql/9.3/main/pg_hba.conf
	echo "Creating PostgreSQL users"
	su postgres -c "source ${SUBMITTY_DIR}/.setup/db_users.sh";
	echo "Finished creating PostgreSQL users"

    echo "Setting up Postgres to connect to via host"
    PG_VERSION="$(psql -V | egrep -o '[0-9]{1,}\.[0-9]{1,}')"
	sed -i "s/#listen_addresses = 'localhost'/listen_addresses = '*'/" "/etc/postgresql/${PG_VERSION}/main/postgresql.conf"
	echo "host    all             all             all                     md5" >> "/etc/postgresql/${PG_VERSION}/main/pg_hba.conf"
	service postgresql restart
fi

#################################################################
# SUBMITTY SETUP
#################

if [ ${VAGRANT} == 1 ]; then
	echo -e "localhost
hsdbu
hsdbu
https://192.168.56.103
svn+ssh:192.168.56.102" | source ${SUBMITTY_DIR}/CONFIGURE.sh
else
	source ${SUBMITTY_DIR}/CONFIGURE.sh
fi

source ${INSTALL_DIR}/INSTALL.sh clean test

source ${SUBMITTY_DIR}/Docs/sample_bin/admin_scripts_setup
cp ${SUBMITTY_DIR}/Docs/sample_apache_config /etc/apache2/sites-available/submit.conf
cp ${SUBMITTY_DIR}/Docs/hwgrading.conf /etc/apache2/sites-available/hwgrading.conf
cp -f ${SUBMITTY_DIR}/Docs/www-data /etc/apache2/suexec/www-data

# permissions: rw- r-- ---
chmod 0640 /etc/apache2/sites-available/*.conf
chmod 0640 /etc/apache2/suexec/www-data

if [ ${VAGRANT} == 1 ]; then
	sed -i 's/SSLCertificateChainFile/#SSLCertificateChainFile/g' /root/bin/bottom.txt
	sed -i 's/course01/csci2600/g' /root/bin/gen.middle
	sed -i 's/hss.crt/submit.crt/g' /etc/apache2/sites-available/submit.conf
	sed -i 's/hss.key/submit.key/g' /etc/apache2/sites-available/submit.conf
	sed -i 's/SSLCertificateChainFile/#SSLCertificateChainFile/g' /etc/apache2/sites-available/hwgrading.conf
	sed -i 's/hwgrading.cer/hwgrading.crt/g' /etc/apache2/sites-available/hwgrading.conf
fi

a2ensite submit
a2ensite hwgrading

apache2ctl -t
service apache2 restart

if [[ ${VAGRANT} == 1 ]]; then
    echo "student" >> ${DATA_DIR}/instructors/authlist
    echo "student" >> ${DATA_DIR}/instructors/valid
    ${DATA_DIR}/bin/authonly.pl
    echo "student:student" | sudo chpasswd

    rm -r ${DATA_DIR}/autograding_logs
    rm -r ${SUBMITTY_DIR}/.vagrant/autograding_logs
    mkdir ${SUBMITTY_DIR}/.vagrant/autograding_logs
    ln -s ${SUBMITTY_DIR}/.vagrant/autograding_logs ${DATA_DIR}/autograding_logs
    rm -r ${DATA_DIR}/tagrading_logs
    rm -r ${SUBMITTY_DIR}/.vagrant/tagrading_logs
    mkdir ${SUBMITTY_DIR}/.vagrant/tagrading_logs
    ln -s ${SUBMITTY_DIR}/.vagrant/tagrading_logs ${DATA_DIR}/tagrading_logs

    #################################################################
    # CRON SETUP
    #################
    #cd /home/hwcron
    #echo "" > /home/hwcron/x
    #sudo cp /home/hwcron/x /var/spool/cron/crontabs/hwcron
    #sudo chown hwcron:crontab /var/spool/cron/crontabs/hwcron
    #echo "0,15,30,45 * * * * /usr/local/hss/bin/grade_students.sh" > /home/hwcron/c
    #su hwcron << EOF
    #  cat /home/hwcron/c | crontab -
    #EOF
    #rm /home/hwcron/x
    #rm /home/hwcron/c

    #################################################################
    # COURSE SETUP
    #################
    cd ${INSTALL_DIR}/bin
    ./create_course.sh f15 csci1100 instructor csci1100_tas_www
    ./create_course.sh f15 csci1200 instructor csci1200_tas_www
    ./create_course.sh f15 csci2600 instructor csci2600_tas_www

    cd ${DATA_DIR}/courses/f15/csci1100
    ./BUILD_csci1100.sh

    cd ${DATA_DIR}/courses/f15/csci1200
    ./BUILD_csci1200.sh

    cd ${DATA_DIR}/courses/f15/csci2600
    ./BUILD_csci2600.sh

    #################################################################
    # CREATE DATABASE
    #################

	export PGPASSWORD="hsdbu"

	psql -d postgres -h localhost -U hsdbu -c "CREATE DATABASE hss_csci1100_f15;"
	psql -d postgres -h localhost -U hsdbu -c "CREATE DATABASE hss_csci1200_f15;"
	psql -d postgres -h localhost -U hsdbu -c "CREATE DATABASE hss_csci2600_f15;"

	psql -d hss_csci1100_f15 -h localhost -U hsdbu -f ${SUBMITTY_DIR}/TAGradingServer/data/tables.sql
	psql -d hss_csci1100_f15 -h localhost -U hsdbu -f ${SUBMITTY_DIR}/TAGradingServer/data/inserts.sql
	psql -d hss_csci1200_f15 -h localhost -U hsdbu -f ${SUBMITTY_DIR}/TAGradingServer/data/tables.sql
	psql -d hss_csci1200_f15 -h localhost -U hsdbu -f ${SUBMITTY_DIR}/TAGradingServer/data/inserts.sql
	psql -d hss_csci2600_f15 -h localhost -U hsdbu -f ${SUBMITTY_DIR}/TAGradingServer/data/tables.sql
	psql -d hss_csci2600_f15 -h localhost -U hsdbu -f ${SUBMITTY_DIR}/TAGradingServer/data/inserts.sql

	psql -d hss_csci1100_f15 -h localhost -U hsdbu -f ${SUBMITTY_DIR}/.setup/vagrant/db_inserts.sql
	psql -d hss_csci1200_f15 -h localhost -U hsdbu -f ${SUBMITTY_DIR}/.setup/vagrant/db_inserts.sql
	psql -d hss_csci2600_f15 -h localhost -U hsdbu -f ${SUBMITTY_DIR}/.setup/vagrant/db_inserts.sql

    #################################################################
    # SET CSV FIELDS (for classlist upload data)
    #################

	# Vagrant auto-settings are based on Rensselaer Polytechnic Institute School
	# of Science 2015-2016.

	# Other Universities will need to rerun /bin/setcsvfields to match their
	# classlist csv data.  See wiki for details.
	${INSTALL_DIR}/bin/setcsvfields 13 12 15 7
fi

# Deferred ownership change
chown hwphp:hwphp ${INSTALL_DIR}
chmod 2771 ${INSTALL_DIR}

echo "Done."
exit 0
