#!/usr/bin/env bash

# Usage:
#   install_system.sh [--vagrant] [<extra> <extra> ...]

# this script must be run by root or sudo
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit
fi

# TIMEZONE
timedatectl set-timezone America/New_York

#################################################################
# CONSTANTS
#################

# PATHS
SOURCE="${BASH_SOURCE[0]}"
CURRENT_DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"
SUBMITTY_REPOSITORY=/usr/local/submitty/GIT_CHECKOUT_Submitty
SUBMITTY_INSTALL_DIR=/usr/local/submitty
SUBMITTY_DATA_DIR=/var/local/submitty

# Groups
COURSE_BUILDERS_GROUP=course_builders

#################################################################
# PROVISION SETUP
#################

if [[ $1 == "--vagrant" ]]; then
  echo "Non-interactive vagrant script..."
  export VAGRANT=1
  export DEBIAN_FRONTEND=noninteractive
  shift
else
  #TODO: We should get options for ./.setup/CONFIGURE_SUBMITTY.py script
  export VAGRANT=0
fi

#################################################################
# DISTRO SETUP
#################

source ${CURRENT_DIR}/distro_setup/setup_distro.sh

#################################################################
# STACK SETUP
#################

if [ ${VAGRANT} == 1 ]; then
    # We only might build analysis tools from source while using vagrant
    echo "Installing stack (haskell)"
    curl -sSL https://get.haskellstack.org/ | sh
fi

#################################################################
# USERS SETUP
#################

${SUBMITTY_REPOSITORY}/.setup/bin/create_untrusted_users.py

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

addgroup ${COURSE_BUILDERS_GROUP}

if [ ${VAGRANT} == 1 ]; then
	adduser vagrant sudo
fi

# change the default user umask (was 002)
sed -i  "s/^UMASK.*/UMASK 027/g"  /etc/login.defs
grep -q "^UMASK 027" /etc/login.defs || (echo "ERROR! failed to set umask" && exit)

adduser hwphp --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
adduser hwphp hwcronphp

adduser hwcgi --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
adduser hwcgi hwphp
# NOTE: hwcgi must be in the shadow group so that it has access to the
# local passwords for pam authentication
adduser hwcgi shadow

adduser hwcron --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
adduser hwcron hwcronphp

# FIXME:  umask setting above not complete
# might need to also set USERGROUPS_ENAB to "no", and manually create
# the hwphp and hwcron single user groups.  See also /etc/login.defs
echo -e "\n# set by the .setup/install_system.sh script\numask 027" >> /home/hwphp/.profile
echo -e "\n# set by the .setup/install_system.sh script\numask 027" >> /home/hwcgi/.profile
echo -e "\n# set by the .setup/install_system.sh script\numask 027" >> /home/hwcron/.profile

adduser hsdbu --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password

if [ ${VAGRANT} == 1 ]; then
	# add these users so that they can write to .vagrant/logs folder
	adduser hwphp vagrant
	adduser hwcgi vagrant
	adduser hwcron vagrant
fi

# TODO: We should look into making it so that only certain users have access to certain packages
# so that hwphp is the only one who could use PAM for example
pip2 install -U pip
pip2 install python-pam
pip2 install psycopg2
pip2 install PyYAML
pip2 install sqlalchemy
pip2 install python-dateutil

pip3 install -U pip
pip3 install python-pam
pip3 install PyYAML
pip3 install psycopg2
pip3 install sqlalchemy
pip3 install pylint
pip3 install psutil
pip3 install python-dateutil
pip3 install watchdog
pip3 install xlsx2csv

pushd ${SUBMITTY_REPOSITORY}/python_submitty_utils
python2 setup.py install
python3 setup.py install
popd

chmod -R 555 /usr/local/lib/python*/*
chmod 555 /usr/lib/python*/dist-packages
sudo chmod 500   /usr/local/lib/python*/dist-packages/pam.py*
sudo chown hwcgi /usr/local/lib/python*/dist-packages/pam.py*


#################################################################
# JAR SETUP
#################

pushd /tmp > /dev/null

echo "Getting JUnit..."
JUNIT_VER=4.12
HAMCREST_VER=1.3
mkdir -p ${SUBMITTY_INSTALL_DIR}/JUnit
chown root:${COURSE_BUILDERS_GROUP} ${SUBMITTY_INSTALL_DIR}/JUnit
chmod 751 ${SUBMITTY_INSTALL_DIR}/JUnit
cd ${SUBMITTY_INSTALL_DIR}/JUnit

wget http://search.maven.org/remotecontent?filepath=junit/junit/${JUNIT_VER}/junit-${JUNIT_VER}.jar -o /dev/null > /dev/null 2>&1
mv remotecontent?filepath=junit%2Fjunit%2F${JUNIT_VER}%2Fjunit-${JUNIT_VER}.jar junit-${JUNIT_VER}.jar
wget http://search.maven.org/remotecontent?filepath=org/hamcrest/hamcrest-core/${HAMCREST_VER}/hamcrest-core-${HAMCREST_VER}.jar -o /dev/null > /dev/null 2>&1
mv remotecontent?filepath=org%2Fhamcrest%2Fhamcrest-core%2F${HAMCREST_VER}%2Fhamcrest-core-${HAMCREST_VER}.jar hamcrest-core-${HAMCREST_VER}.jar

popd > /dev/null

# EMMA is a tool for computing code coverage of Java programs

echo "Getting emma..."

pushd ${SUBMITTY_INSTALL_DIR}/JUnit > /dev/null

EMMA_VER=2.0.5312
wget https://github.com/Submitty/emma/releases/download/${EMMA_VER}/emma-${EMMA_VER}.zip -o /dev/null > /dev/null 2>&1
unzip emma-${EMMA_VER}.zip > /dev/null
mv emma-${EMMA_VER}/lib/emma.jar emma.jar
rm -rf emma-${EMMA_VER}
rm emma-${EMMA_VER}.zip
rm index.html* > /dev/null 2>&1

chmod o+r . *.jar

popd > /dev/null

#################################################################
# DRMEMORY SETUP
#################

# Dr Memory is a tool for detecting memory errors in C++ programs (similar to Valgrind)

pushd /tmp > /dev/null

echo "Getting DrMemory..."
DRMEM_TAG=release_1.10.1
DRMEM_VER=1.10.1-3
wget https://github.com/DynamoRIO/drmemory/releases/download/${DRMEM_TAG}/DrMemory-Linux-${DRMEM_VER}.tar.gz -o /dev/null > /dev/null 2>&1
tar -xpzf DrMemory-Linux-${DRMEM_VER}.tar.gz
mv /tmp/DrMemory-Linux-${DRMEM_VER} ${SUBMITTY_INSTALL_DIR}/drmemory
rm -rf /tmp/DrMemory*
chown -R root:${COURSE_BUILDERS_GROUP} ${SUBMITTY_INSTALL_DIR}/drmemory
chmod 755 ${SUBMITTY_INSTALL_DIR}/drmemory

popd > /dev/null

#################################################################
# APACHE SETUP
#################

a2enmod include actions cgi suexec authnz_external headers ssl fastcgi

# A real user will have to do these steps themselves for a non-vagrant setup as to do it in here would require
# asking the user questions as well as searching the filesystem for certificates, etc.
if [ ${VAGRANT} == 1 ]; then
    # comment out directory configs - should be converted to something more flexible
    sed -i '153,174s/^/#/g' /etc/apache2/apache2.conf

    # remove default sites which would cause server to mess up
    rm /etc/apache2/sites*/000-default.conf
    rm /etc/apache2/sites*/default-ssl.conf

    cp ${SUBMITTY_REPOSITORY}/.setup/vagrant/pool.d/submitty.conf /etc/php/7.0/fpm/pool.d/submitty.conf
    cp ${SUBMITTY_REPOSITORY}/.setup/vagrant/sites-available/submitty.conf /etc/apache2/sites-available/submitty.conf

    # permissions: rw- r-- ---
    chmod 0640 /etc/apache2/sites-available/*.conf
    a2ensite submitty

    sed -i '25s/^/\#/' /etc/pam.d/common-password
	sed -i '26s/pam_unix.so obscure use_authtok try_first_pass sha512/pam_unix.so obscure minlen=1 sha512/' /etc/pam.d/common-password
fi

cp ${SUBMITTY_REPOSITORY}/.setup/apache/www-data /etc/apache2/suexec/www-data
chmod 0640 /etc/apache2/suexec/www-data


#################################################################
# PHP SETUP
#################

# Edit php settings.  Note that if you need to accept larger files,
# you’ll need to increase both upload_max_filesize and
# post_max_filesize

sed -i -e 's/^max_execution_time = 30/max_execution_time = 60/g' /etc/php/7.0/fpm/php.ini
sed -i -e 's/^upload_max_filesize = 2M/upload_max_filesize = 10M/g' /etc/php/7.0/fpm/php.ini
sed -i -e 's/^session.gc_maxlifetime = 1440/session.gc_maxlifetime = 86400/' /etc/php/7.0/fpm/php.ini
sed -i -e 's/^post_max_size = 8M/post_max_size = 10M/g' /etc/php/7.0/fpm/php.ini
sed -i -e 's/^allow_url_fopen = On/allow_url_fopen = Off/g' /etc/php/7.0/fpm/php.ini
sed -i -e 's/^session.cookie_httponly =/session.cookie_httponly = 1/g' /etc/php/7.0/fpm/php.ini
# This should mimic the list of disabled functions that RPI uses on the HSS machine with the sole difference
# being that we do not disable phpinfo() on the vagrant machine as it's not a function that could be used for
# development of some feature, but it is useful for seeing information that could help debug something going wrong
# with our version of PHP.
DISABLED_FUNCTIONS="popen,pclose,proc_open,chmod,php_real_logo_guid,php_egg_logo_guid,php_ini_scanned_files,"
DISABLED_FUNCTIONS+="php_ini_loaded_file,readlink,symlink,link,set_file_buffer,proc_close,proc_terminate,"
DISABLED_FUNCTIONS+="proc_get_status,proc_nice,getmyuid,getmygid,getmyinode,putenv,get_current_user,"
DISABLED_FUNCTIONS+="magic_quotes_runtime,set_magic_quotes_runtime,import_request_variables,ini_alter,"
DISABLED_FUNCTIONS+="stream_socket_client,stream_socket_server,stream_socket_accept,stream_socket_pair,"
DISABLED_FUNCTIONS+="stream_get_transports,stream_wrapper_restore,mb_send_mail,openlog,syslog,closelog,pfsockopen,"
DISABLED_FUNCTIONS+="posix_kill,apache_child_terminate,apache_get_modules,apache_get_version,apache_lookup_uri,"
DISABLED_FUNCTIONS+="apache_reset_timeout,apache_response_headers,virtual,system,exec,shell_exec,passthru,"
DISABLED_FUNCTIONS+="pcntl_alarm,pcntl_fork,pcntl_waitpid,pcntl_wait,pcntl_wifexited,pcntl_wifstopped,"
DISABLED_FUNCTIONS+="pcntl_wifsignaled,pcntl_wexitstatus,pcntl_wtermsig,pcntl_wstopsig,pcntl_signal,"
DISABLED_FUNCTIONS+="pcntl_signal_dispatch,pcntl_get_last_error,pcntl_strerror,pcntl_sigprocmask,pcntl_sigwaitinfo,"
DISABLED_FUNCTIONS+="pcntl_sigtimedwait,pcntl_exec,pcntl_getpriority,pcntl_setpriority,"

if [ ${VAGRANT} != 1 ]; then
    DISABLED_FUNCTIONS+="phpinfo,"
fi

sed -i -e "s/^disable_functions = .*/disable_functions = ${DISABLED_FUNCTIONS}/g" /etc/php/7.0/fpm/php.ini

# create directories and fix permissions
mkdir -p ${SUBMITTY_DATA_DIR}

# create a list of valid userids and put them in /var/local/submitty/instructors
# one way to create your list is by listing all of the userids in /home
mkdir -p ${SUBMITTY_DATA_DIR}/instructors
ls /home | sort > ${SUBMITTY_DATA_DIR}/instructors/valid

#################################################################
# POSTGRES SETUP
#################
if [ ${VAGRANT} == 1 ]; then
	service postgresql restart
	PG_VERSION="$(psql -V | egrep -o '[0-9]{1,}.[0-9]{1,}')"
	cp /etc/postgresql/${PG_VERSION}/main/pg_hba.conf /etc/postgresql/${PG_VERSION}/main/pg_hba.conf.backup
	cp ${SUBMITTY_REPOSITORY}/.setup/vagrant/pg_hba.conf /etc/postgresql/${PG_VERSION}/main/pg_hba.conf
	echo "Creating PostgreSQL users"
	su postgres -c "source ${SUBMITTY_REPOSITORY}/.setup/vagrant/db_users.sh";
	echo "Finished creating PostgreSQL users"

	sed -i "s/#listen_addresses = 'localhost'/listen_addresses = '*'/" "/etc/postgresql/${PG_VERSION}/main/postgresql.conf"
	service postgresql restart
fi


#################################################################
# CLONE THE TUTORIAL REPO
#################

# grab the tutorial repo, which includes a number of curated example
# assignment configurations

if [ -d ${SUBMITTY_INSTALL_DIR}/GIT_CHECKOUT_Tutorial ]; then
    pushd ${SUBMITTY_INSTALL_DIR}/GIT_CHECKOUT_Tutorial
    git pull
    popd
else
    git clone 'https://github.com/Submitty/Tutorial' ${SUBMITTY_INSTALL_DIR}/GIT_CHECKOUT_Tutorial
    pushd ${SUBMITTY_INSTALL_DIR}/GIT_CHECKOUT_Tutorial
    # remember to change this version in .setup/travis/autograder.sh too
    git checkout v0.91
    popd
fi


#################################################################
# ANALYSIS TOOLS SETUP
#################

if [ -d ${SUBMITTY_INSTALL_DIR}/GIT_CHECKOUT_AnalysisTools ]; then
    pushd ${SUBMITTY_INSTALL_DIR}/GIT_CHECKOUT_AnalysisTools
    git pull
    popd
else
    git clone 'https://github.com/Submitty/AnalysisTools' ${SUBMITTY_INSTALL_DIR}/GIT_CHECKOUT_AnalysisTools
fi


#################################################################
# SUBMITTY SETUP
#################


if [ ${VAGRANT} == 1 ]; then
    # This should be set by setup_distro.sh for whatever distro we have, but
    # in case it is not, default to our primary URL
    if [ -z "${SUBMISSION_URL}" ]; then
        SUBMISSION_URL='http://192.168.56.101'
    fi
    echo -e "/var/run/postgresql
hsdbu
hsdbu
${SUBMISSION_URL}
1" | ${SUBMITTY_REPOSITORY}/.setup/CONFIGURE_SUBMITTY.py --debug

else
	${SUBMITTY_REPOSITORY}/.setup/CONFIGURE_SUBMITTY.py
fi

source ${SUBMITTY_INSTALL_DIR}/.setup/INSTALL_SUBMITTY.sh clean


# (re)start the submitty grading scheduler daemon
systemctl restart submitty_grading_scheduler
# also, set it to automatically start on boot
sudo systemctl enable submitty_grading_scheduler



mkdir -p ${SUBMITTY_DATA_DIR}/instructors
mkdir -p ${SUBMITTY_DATA_DIR}/bin
touch ${SUBMITTY_DATA_DIR}/instructors/authlist
touch ${SUBMITTY_DATA_DIR}/instructors/valid
[ ! -f ${SUBMITTY_DATA_DIR}/bin/authonly.pl ] && cp ${SUBMITTY_REPOSITORY}/Docs/sample_bin/authonly.pl ${SUBMITTY_DATA_DIR}/bin/authonly.pl
[ ! -f ${SUBMITTY_DATA_DIR}/bin/validate.auth.pl ] && cp ${SUBMITTY_REPOSITORY}/Docs/sample_bin/validate.auth.pl ${SUBMITTY_DATA_DIR}/bin/validate.auth.pl
chmod 660 ${SUBMITTY_DATA_DIR}/instructors/authlist
chmod 640 ${SUBMITTY_DATA_DIR}/instructors/valid

sudo mkdir -p /usr/lib/cgi-bin
sudo chown -R www-data:www-data /usr/lib/cgi-bin

apache2ctl -t

PGPASSWORD=hsdbu psql -d postgres -h localhost -U hsdbu -c "CREATE DATABASE submitty"
PGPASSWORD=hsdbu psql -d submitty -h localhost -U hsdbu -f ${SUBMITTY_REPOSITORY}/site/data/submitty_db.sql

if [[ ${VAGRANT} == 1 ]]; then
    # Disable OPCache for development purposes as we don't care about the efficiency as much
    echo "opcache.enable=0" >> /etc/php/7.0/fpm/conf.d/10-opcache.ini

    DISTRO=$(lsb_release -i | sed -e "s/Distributor\ ID\:\t//g")

    rm -rf ${SUBMITTY_DATA_DIR}/logs/*
    rm -rf ${SUBMITTY_REPOSITORY}/.vagrant/${DISTRO}/logs/submitty
    mkdir -p ${SUBMITTY_REPOSITORY}/.vagrant/${DISTRO}/logs/submitty
    mkdir -p ${SUBMITTY_REPOSITORY}/.vagrant/${DISTRO}/logs/submitty/autograding
    ln -s ${SUBMITTY_REPOSITORY}/.vagrant/${DISTRO}/logs/submitty/autograding ${SUBMITTY_DATA_DIR}/logs/autograding
    chown hwcron:course_builders ${SUBMITTY_DATA_DIR}/logs/autograding
    chmod 770 ${SUBMITTY_DATA_DIR}/logs/autograding

    mkdir -p ${SUBMITTY_REPOSITORY}/.vagrant/${DISTRO}/logs/submitty/access
    mkdir -p ${SUBMITTY_REPOSITORY}/.vagrant/${DISTRO}/logs/submitty/site_errors
    ln -s ${SUBMITTY_REPOSITORY}/.vagrant/${DISTRO}/logs/submitty/access ${SUBMITTY_DATA_DIR}/logs/access
    ln -s ${SUBMITTY_REPOSITORY}/.vagrant/${DISTRO}/logs/submitty/site_errors ${SUBMITTY_DATA_DIR}/logs/site_errors
    chown -R hwphp:course_builders ${SUBMITTY_DATA_DIR}/logs/access
    chmod -R 770 ${SUBMITTY_DATA_DIR}/logs/access
    chown -R hwphp:course_builders ${SUBMITTY_DATA_DIR}/logs/site_errors
    chmod -R 770 ${SUBMITTY_DATA_DIR}/logs/site_errors

    # Call helper script that makes the courses and refreshes the database
    ${SUBMITTY_REPOSITORY}/.setup/bin/setup_sample_courses.py --submission_url ${SUBMISSION_URL}

    #################################################################
    # SET CSV FIELDS (for classlist upload data)
    #################
    # Vagrant auto-settings are based on Rensselaer Polytechnic Institute School
    # of Science 2015-2016.

    # Other Universities will need to rerun /bin/setcsvfields to match their
    # classlist csv data.  See wiki for details.
    ${SUBMITTY_INSTALL_DIR}/bin/setcsvfields 13 12 15 7
fi


#################################################################
# RESTART SERVICES
###################

service apache2 restart
service php7.0-fpm restart
service postgresql restart


echo "Done."
exit 0
