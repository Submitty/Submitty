#!/usr/bin/env bash

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
##  Happy developing!                                     ##
############################################################
' > /etc/motd
chmod +rx /etc/motd

echo "192.168.56.101    test-submit test-submit.cs.rpi.edu" >> /etc/hosts
echo "192.168.56.102    test-svn test-svn.cs.rpi.edu" >> /etc/hosts
echo "192.168.56.103    test-hwgrading test-hwgrading.cs.rpi.edu hwgrading" >> /etc/hosts

#################################################################
# PACKAGE SETUP
#################
echo "\n" | add-apt-repository ppa:webupd8team/java
apt-get -qq update

apt-get install -qqy ntp
service ntp restart

# path for untrusted user creation script will be different if not using Vagrant
/vagrant/bin/create.untrusted.users.pl

apt-get install -qqy libpam-passwdqc

apt-get install -qqy ssh sshpass unzip
apt-get install -qqy apache2 postgresql postgresql-contrib php5 php5-xdebug libapache2-mod-suphp

apachectl -V | grep MPM

echo "Preparing to install packages.  This may take a while."
apt-get install -qqy clang autoconf automake autotools-dev clisp diffstat emacs finger gdb git git-man \
hardening-includes python p7zip-full patchutils postgresql-client postgresql-client-9.3 postgresql-client-common \
unzip valgrind zip libmagic-ocaml-dev common-lisp-controller libboost-all-dev javascript-common \
apache2-suexec-custom libapache2-mod-authnz-external libapache2-mod-authz-unixgroup libfile-mmagic-perl \
libgnupg-interface-perl php5-pgsql libbsd-resource-perl libarchive-zip-perl gcc g++ g++-multilib jq libseccomp-dev \
libseccomp2 seccomp junit cmake

# Install Oracle 8 Non-Interactively
echo oracle-java8-installer shared/accepted-oracle-license-v1-1 select true | sudo /usr/bin/debconf-set-selections
apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys EEA14886
echo "apt-get install -qqy oracle-java8-installer..."
apt-get install -qqy oracle-java8-installer > /dev/null 2>&1

#################################################################
# JAR SETUP
#################
echo "Getting JUnit..."
mkdir -p /usr/local/hss/JUnit
cd /usr/local/hss/JUnit

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
mkdir -p /usr/local/hss/DrMemory
wget http://dl.bintray.com/bruening/DrMemory/DrMemory-Linux-1.8.0-8.tar.gz -o /dev/null > /dev/null 2>&1
tar -xpzf DrMemory-Linux-1.8.0-8.tar.gz
ln -s /usr/local/hss/DrMemory/DrMemory-Linux-1.8.0-8 /usr/local/hss/drmemory

#################################################################
# APACHE SETUP
#################
a2enmod include actions suexec authnz_external headers ssl

mkdir /etc/apache2/ssl
cd /etc/apache2/ssl
echo -e "US\nNew York\nTroy\nRPI\nCSCI\n.\n." | openssl req -x509 -nodes -days 365000 -newkey rsa:2048 -keyout submit.key -out submit.crt
echo -e "US\nNew York\nTroy\nRPI\nCSCI\n.\n." | openssl req -x509 -nodes -days 365000 -newkey rsa:2048 -keyout hwgrading.key -out hwgrading.crt
echo -e "US\nNew York\nTroy\nRPI\nCSCI\n.\n." | openssl req -x509 -nodes -days 365000 -newkey rsa:2048 -keyout svn.key -out svn.crt
chmod o+r hwgrading.crt
chmod o+r submit.crt
chmod o+r svn.crt

echo -e "#%PAM-1.0
auth required pam_unix.so
account required pam_unix.so" > /etc/pam.d/httpd

# Loosen password requirements
sed -i '25s/^/\#/' /etc/pam.d/common-password
sed -i '26s/pam_unix.so obscure use_authtok try_first_pass sha512/pam_unix.so obscure minlen=1 sha512/' /etc/pam.d/common-password

sed -i '153,174s/^/#/g' /etc/apache2/apache2.conf

# remove default sites which would cause server to mess up
rm /etc/apache2/sites*/000-default.conf
rm /etc/apache2/sites*/default-ssl.conf

echo -e "\nServerName 10.0.2.15\n" >> /etc/apache2/apache2.conf

service apache2 reload

#################################################################
# PHP SETUP
#################
sed -i -e 's/^docroot=/docroot=\/usr\/local\/hss:/g' /etc/suphp/suphp.conf
sed -i -e 's/^allow_file_group_writeable=false/allow_file_group_writeable=true/g' /etc/suphp/suphp.conf
sed -i -e 's/^allow_directory_group_writeable=false/allow_directory_group_writeable=true/g' /etc/suphp/suphp.conf
sed -i -e 's/^max_execution_time = 30/max_execution_time = 60/g' /etc/php5/cgi/php.ini
sed -i -e 's/^upload_max_filesize = 2M/upload_max_filesize = 10M/g' /etc/php5/cgi/php.ini
sed -i -e 's/^post_max_size = 8M/post_max_size = 10M/g' /etc/php5/cgi/php.ini
sed -i -e 's/^allow_url_fopen = On/allow_url_fopen = Off/g' /etc/php5/cgi/php.ini
sed -i -e 's/^session.cookie_httponly =/session.cookie_httponly = 1/g' /etc/php5/cgi/php.ini


#################################################################
# USERS SETUP
#################
adduser vagrant sudo

addgroup hwcronphp
addgroup course_builders
adduser ta --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
echo "ta:ta" | sudo chpasswd
adduser instructor --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
echo "instructor:instructor" | sudo chpasswd
adduser instructor sudo
adduser developer --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
echo "developer:developer" | sudo chpasswd
adduser developer sudo
adduser hwphp --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
echo "hwphp:hwphp" | sudo chpasswd
adduser hwcron --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
echo "hwcron:hwcron" | sudo chpasswd
adduser hsdbu --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
echo "hsdbu:hsdbu" | sudo chpasswd
adduser hwphp hwcronphp
adduser hwcron hwcronphp

# TODO: we can automate this with a loop probably
addgroup csci1100
adduser ta csci1100
adduser instructor csci1100
adduser developer csci1100
addgroup csci1100_tas_www
adduser hwphp csci1100_tas_www
adduser hwcron csci1100_tas_www
adduser ta csci1100_tas_www
adduser instructor csci1100_tas_www
adduser developer csci1100_tas_www

addgroup csci1200
adduser ta csci1200
adduser instructor csci1200
adduser developer csci1200
addgroup csci1200_tas_www
adduser hwphp csci1200_tas_www
adduser hwcron csci1200_tas_www
adduser ta csci1200_tas_www
adduser instructor csci1200_tas_www
adduser developer csci1200_tas_www

addgroup csci2600
adduser ta csci2600
adduser instructor csci2600
adduser developer csci2600
addgroup csci2600_tas_www
adduser hwphp csci2600_tas_www
adduser hwcron csci2600_tas_www
adduser ta csci2600_tas_www
adduser instructor csci2600_tas_www
adduser developer csci2600_tas_www

adduser instructor course_builders

mkdir -p /var/local/hss
mkdir -p /usr/local/hss
chown hwphp:hwphp /usr/local/hss
chmod 2771 /usr/local/hss

mkdir -p /var/local/hss/instructors
ls /home | sort > /var/local/hss/instructors/valid

#################################################################
# SVN SETUP
#################
service apache2 restart
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
su hwcron
echo -e "\n" | ssh-keygen -t rsa -b 4096 -N ""
echo "hwcron" > password.txt
sshpass -f password.txt ssh-copy-id hwcron@test-svn
rm password.txt
echo "csci2600_tas_www: hwcron ta instructor developer" >> /var/lib/svn/svngroups

service apache2 restart

#################################################################
# POSTGRES SETUP
#################
echo "postgres:postgres" | chpasswd postgres
adduser postgres shadow
service postgresql restart
sed -i -e "s/# ----------------------------------/# ----------------------------------\nhostssl    all    all    192.168.56.0\/24    pam\nhost    all    all    192.168.56.0\/24    pam/" /etc/postgresql/9.3/main/pg_hba.conf
echo "Creating PostgreSQL users"
su postgres << EOF
psql -c "
  CREATE ROLE hsdbu WITH SUPERUSER CREATEDB CREATEROLE LOGIN PASSWORD 'hsdbu';
  CREATE ROLE root WITH SUPERUSER CREATEDB CREATEROLE LOGIN PASSWORD 'vagrant';
  CREATE ROLE vagrant WITH SUPERUSER CREATEDB CREATEROLE LOGIN PASSWORD 'vagrant';"
EOF

#################################################################
# HWSERVER SETUP
#################

if [[ ${VAGRANT} == 1 ]]; then
  ln -s /vagrant /usr/local/hss/GIT_CHECKOUT_HWserver
else
  cd /usr/local/hss
  git clone https://github.com/RCOS-Grading-Server/HWserver.git
  mv HWserver GIT_CHECKOUT_HWserver
fi

HWSERVER_DIR=/usr/local/hss/GIT_CHECKOUT_HWserver
cd ${HWSERVER_DIR}

echo -e "localhost
hsdbu
hsdbu
http://192.168.56.103
svn+ssh:192.168.56.102" | source ${HWSERVER_DIR}/CONFIGURE.sh

source ${HWSERVER_DIR}/INSTALL.sh

source ${HWSERVER_DIR}/Docs/sample_bin/admin_scripts_setup
sed -i 's/SSLCertificateChainFile/#SSLCertificateChainFile/g' /root/bin/bottom.txt
sed -i 's/course01/csci2600/g' /root/bin/gen.middle

cp ${HWSERVER_DIR}/Docs/sample_apache_config /etc/apache2/sites-available/submit.conf
sed -i 's/hss.crt/submit.crt/g' /etc/apache2/sites-available/submit.conf
sed -i 's/hss.key/submit.key/g' /etc/apache2/sites-available/submit.conf
cp ${HWSERVER_DIR}/Docs/hwgrading.conf /etc/apache2/sites-available/hwgrading.conf
sed -i 's/SSLCertificateChainFile/#SSLCertificateChainFile/g' /etc/apache2/sites-available/hwgrading.conf
sed -i 's/hwgrading.cer/hwgrading.crt/g' /etc/apache2/sites-available/hwgrading.conf

a2ensite submit
a2ensite hwgrading

apache2ctl -t
service apache2 restart

if [[ ${VAGRANT} == 1 ]]; then
  rm /var/local/hss/autograding_logs
  rm /var/local/hss/tagrading_logs
  rm /vagrant/.vagrant/autograding_logs
  rm /vagrant/.vagrant/tagrading_logs
  ln -s /vagrant/.vagrant/autograding_logs /var/local/hss/autograding_logs
  ln -s /vagrant/.vagrant/tagrading_logs /var/local/hss/tagrading_logs
fi

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
cd ${HWSERVER_DIR}/../bin
./create_course.sh f15 csci1100 instructor csci1100_tas_www
./create_course.sh f15 csci1200 instructor csci1200_tas_www
./create_course.sh f15 csci2600 instructor csci2600_tas_www

cd /var/local/hss/courses/f15/csci1100
./BUILD_csci1100.sh

cd /var/local/hss/courses/f15/csci1200
./BUILD_csci1200.sh

cd /var/local/hss/courses/f15/csci2600
./BUILD_csci2600.sh

#################################################################
# CREATE DATABASE
#################

export PGPASSWORD='hsdbu';

psql -d postgres -h localhost -U hsdbu -c "CREATE DATABASE hss_csci1100_f15;"
psql -d postgres -h localhost -U hsdbu -c "CREATE DATABASE hss_csci1200_f15;"
psql -d postgres -h localhost -U hsdbu -c "CREATE DATABASE hss_csci2600_f15;"

psql -d hss_csci1100_f15 -h localhost -U hsdbu -f ${HWSERVER_DIR}/TAGradingServer/data/tables.sql
psql -d hss_csci1100_f15 -h localhost -U hsdbu -f ${HWSERVER_DIR}/TAGradingServer/data/inserts.sql
psql -d hss_csci1200_f15 -h localhost -U hsdbu -f ${HWSERVER_DIR}/TAGradingServer/data/tables.sql
psql -d hss_csci1200_f15 -h localhost -U hsdbu -f ${HWSERVER_DIR}/TAGradingServer/data/inserts.sql
psql -d hss_csci2600_f15 -h localhost -U hsdbu -f ${HWSERVER_DIR}/TAGradingServer/data/tables.sql
psql -d hss_csci2600_f15 -h localhost -U hsdbu -f ${HWSERVER_DIR}/TAGradingServer/data/inserts.sql
