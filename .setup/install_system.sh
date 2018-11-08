#!/usr/bin/env bash

# Usage:
#   install_system.sh [--vagrant] [--worker] [<extra> <extra> ...]

err_message() {
    >&2 echo -e "
#####################################################################

                        INSTALLATION FAILURE

Something has gone wrong in the installation process. If you feel
that this is in error, please create an issue on our issue tracker at
https://github.com/Submitty/Submitty including an output of the build
log to better help us diagnose what has gone wrong.
#####################################################################
"
}

# Display our error message if something fails below
trap 'err_message' ERR

# print commands as we execute and fail early
set -ev

# this script must be run by root or sudo
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit 1
fi

#################################################################
# CONSTANTS
#################

# PATHS
CURRENT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
SUBMITTY_REPOSITORY=/usr/local/submitty/GIT_CHECKOUT/Submitty
SUBMITTY_INSTALL_DIR=/usr/local/submitty
SUBMITTY_DATA_DIR=/var/local/submitty


# USERS / GROUPS
DAEMON_USER=submitty_daemon
DAEMON_GROUP=submitty_daemon
PHP_USER=submitty_php
PHP_GROUP=submitty_php
CGI_USER=submitty_cgi
CGI_GROUP=submitty_cgi

DAEMONPHP_GROUP=submitty_daemonphp
DAEMONCGI_GROUP=submitty_daemoncgi

# VERSIONS
source ${CURRENT_DIR}/bin/versions.sh

#################################################################
# PROVISION SETUP
#################

export VAGRANT=0
export NO_SUBMISSIONS=0
export WORKER=0

# Read through the flags passed to the script reading them in and setting
# appropriate bash variables, breaking out of this once we hit something we
# don't recognize as a flag
while :; do
    case $1 in
        --vagrant)
            export VAGRANT=1
            ;;
        --worker)
            export WORKER=1
            ;;
        --no_submissions)
            export NO_SUBMISSIONS=1
            echo 'no_submissions'
            ;;
        *) # No more options, so break out of the loop.
            break
    esac

    shift
done


if [ ${VAGRANT} == 1 ]; then
    echo "Non-interactive vagrant script..."

    export DEBIAN_FRONTEND=noninteractive

    # Setting it up to allow SSH as root by default
    mkdir -p -m 700 /root/.ssh
    cp /home/vagrant/.ssh/authorized_keys /root/.ssh

    sed -i -e "s/PermitRootLogin prohibit-password/PermitRootLogin yes/g" /etc/ssh/sshd_config

    # Set up some convinence stuff for the root user on ssh
     echo -e "

# Convinence stuff for Submitty
export SUBMITTY_REPOSITORY=${SUBMITTY_REPOSITORY}
export SUBMITTY_INSTALL_DIR=${SUBMITTY_INSTALL_DIR}
export SUBMITTY_DATA_DIR=${SUBMITTY_DATA_DIR}
alias install_submitty='/usr/local/submitty/.setup/INSTALL_SUBMITTY.sh'
alias install_submitty_site='bash /usr/local/submitty/GIT_CHECKOUT/Submitty/.setup/INSTALL_SUBMITTY_HELPER_SITE.sh'
alias install_submitty_bin='bash /usr/local/submitty/GIT_CHECKOUT/Submitty/.setup/INSTALL_SUBMITTY_HELPER_BIN.sh'
alias submitty_code_watcher='python3 /usr/local/submitty/GIT_CHECKOUT/Submitty/.setup/bin/code_watcher.py'
cd ${SUBMITTY_INSTALL_DIR}" >> /root/.bashrc
else
    #TODO: We should get options for ./.setup/CONFIGURE_SUBMITTY.py script
    :
fi

if [ ${WORKER} == 1 ]; then
    echo "Installing Submitty in worker mode."
else
    echo "Installing primary Submitty."

fi

COURSE_BUILDERS_GROUP=submitty_course_builders
DB_USER=submitty_dbuser
DATABASE_PASSWORD=submitty_dbuser

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

python3 ${SUBMITTY_REPOSITORY}/.setup/bin/create_untrusted_users.py

# Special users and groups needed to run Submitty
#
# It is probably easiest to set and store passwords for the special
# accounts, although you can also use ‘sudo su user’ to change to the
# desired user on the local machine which works for most things.

# The group DAEMONPHP_GROUP allows the PHP_USER to write the
# submissions, but give read-only access to the DAEMON_USER.  And the
# DAEMON_USER writes the results, and gives read-only access to the
# PHP_USER.

if ! cut -d ':' -f 1 /etc/group | grep -q ${DAEMONPHP_GROUP} ; then
	addgroup ${DAEMONPHP_GROUP}
else
	echo "${DAEMONPHP_GROUP} already exists"
fi

if ! cut -d ':' -f 1 /etc/group | grep -q ${DAEMONCGI_GROUP} ; then
    addgroup ${DAEMONCGI_GROUP}
else
    echo "${DAEMONCGI_GROUP} already exists"
fi

# The COURSE_BUILDERS_GROUP allows instructors/head TAs/course
# managers to write website custimization files and run course
# management scripts.
if ! cut -d ':' -f 1 /etc/group | grep -q ${COURSE_BUILDERS_GROUP} ; then
        addgroup ${COURSE_BUILDERS_GROUP}
else
        echo "${COURSE_BUILDERS_GROUP} already exists"
fi

if [ ${VAGRANT} == 1 ]; then
	adduser vagrant sudo
fi

# change the default user umask (was 002)
sed -i  "s/^UMASK.*/UMASK 027/g"  /etc/login.defs
grep -q "^UMASK 027" /etc/login.defs || (echo "ERROR! failed to set umask" && exit)

#add users not needed on a worker machine.
if [ ${WORKER} == 0 ]; then
    if ! cut -d ':' -f 1 /etc/passwd | grep -q ${PHP_USER} ; then
        adduser "${PHP_USER}" --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
    fi
    usermod -a -G "${DAEMONPHP_GROUP}" "${PHP_USER}"
    if ! cut -d ':' -f 1 /etc/passwd | grep -q ${CGI_USER} ; then
        adduser "${CGI_USER}" --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
    fi
    usermod -a -G "${PHP_GROUP}" "${CGI_USER}"
    usermod -a -G "${DAEMONCGI_GROUP}" "${CGI_USER}"
    # THIS USER SHOULD NOT BE NECESSARY AS A UNIX GROUP
    #adduser "${DB_USER}" --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password

    # NOTE: ${CGI_USER} must be in the shadow group so that it has access to the
    # local passwords for pam authentication
    usermod -a -G shadow "${CGI_USER}"
    # FIXME:  umask setting above not complete
    # might need to also set USERGROUPS_ENAB to "no", and manually create
    # the PHP_GROUP and DAEMON_GROUP single user groups.  See also /etc/login.defs
    echo -e "\n# set by the .setup/install_system.sh script\numask 027" >> /home/${PHP_USER}/.profile
    echo -e "\n# set by the .setup/install_system.sh script\numask 027" >> /home/${CGI_USER}/.profile
fi

if ! cut -d ':' -f 1 /etc/passwd | grep -q ${DAEMON_USER} ; then
    adduser "${DAEMON_USER}" --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
fi

# The VCS directores (/var/local/submitty/vcs) are owfned by root:$DAEMONCGI_GROUP
usermod -a -G "${DAEMONPHP_GROUP}" "${DAEMON_USER}"
usermod -a -G "${DAEMONCGI_GROUP}" "${DAEMON_USER}"

echo -e "\n# set by the .setup/install_system.sh script\numask 027" >> /home/${DAEMON_USER}/.profile

if [ ${VAGRANT} == 1 ]; then
    # add these users so that they can write to .vagrant/logs folder
    if [ ${WORKER} == 0 ]; then
        usermod -a -G vagrant "${PHP_USER}"
        usermod -a -G vagrant "${CGI_USER}"
    fi
    usermod -a -G vagrant "${DAEMON_USER}"
fi

usermod -a -G docker "${DAEMON_USER}"

pip3 install -U pip
pip3 install python-pam
pip3 install PyYAML
pip3 install psycopg2-binary
pip3 install sqlalchemy
pip3 install pylint
pip3 install psutil
pip3 install python-dateutil
pip3 install watchdog
pip3 install xlsx2csv
pip3 install pause
pip3 install paramiko
pip3 install tzlocal
pip3 install PyPDF2
pip3 install distro

# for Lichen / Plagiarism Detection
pip3 install parso

# Python3 implementation of python-clang bindings (may not work < 6.0)
pip3 install clang

#libraries for QR code processing:
#install DLL for zbar
apt-get install libzbar0 --yes

pip3 install pyzbar
pip3 install pdf2image

sudo chmod -R 555 /usr/local/lib/python*/*
sudo chmod 555 /usr/lib/python*/dist-packages
sudo chmod 500 /usr/local/lib/python*/dist-packages/pam.py*

if [ ${WORKER} == 0 ]; then
    sudo chown ${CGI_USER} /usr/local/lib/python*/dist-packages/pam.py*
fi

#################################################################
# JAR SETUP
#################

pushd /tmp > /dev/null

# -----------------------------------------
echo "Getting JUnit & Hamcrest..."

mkdir -p ${SUBMITTY_INSTALL_DIR}/JUnit

if [ ${WORKER} == 0 ]; then
    chown root:${COURSE_BUILDERS_GROUP} ${SUBMITTY_INSTALL_DIR}/JUnit
fi

chmod 751 ${SUBMITTY_INSTALL_DIR}/JUnit
cd ${SUBMITTY_INSTALL_DIR}/JUnit

wget http://repo1.maven.org/maven2/junit/junit/${JUNIT_VERSION}/junit-${JUNIT_VERSION}.jar -o /dev/null > /dev/null 2>&1
wget http://repo1.maven.org/maven2/org/hamcrest/hamcrest-core/${HAMCREST_VERSION}/hamcrest-core-${HAMCREST_VERSION}.jar -o /dev/null > /dev/null 2>&1

# TODO:  Want to Install JUnit 5.0
# And maybe also Hamcrest 2.0 (or maybe that piece isn't needed anymore)

popd > /dev/null


# EMMA is a tool for computing code coverage of Java programs
echo "Getting emma..."

pushd ${SUBMITTY_INSTALL_DIR}/JUnit > /dev/null
wget https://github.com/Submitty/emma/archive/${EMMA_VERSION}.zip -O emma-${EMMA_VERSION}.zip -o /dev/null > /dev/null 2>&1
unzip emma-${EMMA_VERSION}.zip > /dev/null
mv emma-${EMMA_VERSION}/lib/emma.jar emma.jar
rm -rf emma-${EMMA_VERSION}*
chmod o+r . *.jar
popd > /dev/null

# JaCoCo is a potential replacement for EMMA

echo "Getting JaCoCo..."

pushd ${SUBMITTY_INSTALL_DIR}/JUnit > /dev/null
wget https://github.com/jacoco/jacoco/releases/download/v${JACOCO_VERSION}/jacoco-${JACOCO_VERSION}.zip -o /dev/null > /dev/null 2>&1
mkdir jacoco-${JACOCO_VERSION}
unzip jacoco-${JACOCO_VERSION}.zip -d jacoco-${JACOCO_VERSION} > /dev/null
mv jacoco-${JACOCO_VERSION}/lib/jacococli.jar jacococli.jar
mv jacoco-${JACOCO_VERSION}/lib/jacocoagent.jar jacocoagent.jar
rm -rf jacoco-${JACOCO_VERSION}
rm -f jacoco-${JACOCO_VERSION}.zip
chmod o+r . *.jar
popd > /dev/null


#################################################################
# DRMEMORY SETUP
#################

# Dr Memory is a tool for detecting memory errors in C++ programs (similar to Valgrind)

pushd /tmp > /dev/null

echo "Getting DrMemory..."

wget https://github.com/DynamoRIO/drmemory/releases/download/${DRMEMORY_TAG}/DrMemory-Linux-${DRMEMORY_VERSION}.tar.gz -o /dev/null > /dev/null 2>&1
tar -xpzf DrMemory-Linux-${DRMEMORY_VERSION}.tar.gz
rsync --delete -a /tmp/DrMemory-Linux-${DRMEMORY_VERSION}/ ${SUBMITTY_INSTALL_DIR}/drmemory
rm -rf /tmp/DrMemory*

chown -R root:${COURSE_BUILDERS_GROUP} ${SUBMITTY_INSTALL_DIR}/drmemory
chmod -R 755 ${SUBMITTY_INSTALL_DIR}/drmemory

popd > /dev/null

#################################################################
# APACHE SETUP
#################

#Set up website if not in worker mode
if [ ${WORKER} == 0 ]; then
    PHP_VERSION=$(php -r 'print PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')

    # install composer which is needed for the website
    curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php

    a2enmod include actions cgi suexec authnz_external headers ssl proxy_fcgi

    # A real user will have to do these steps themselves for a non-vagrant setup as to do it in here would require
    # asking the user questions as well as searching the filesystem for certificates, etc.
    if [ ${VAGRANT} == 1 ]; then
        # comment out directory configs - should be converted to something more flexible
        sed -i '153,174s/^/#/g' /etc/apache2/apache2.conf

        # remove default sites which would cause server to mess up
        rm /etc/apache2/sites*/000-default.conf
        rm /etc/apache2/sites*/default-ssl.conf

        cp ${SUBMITTY_REPOSITORY}/.setup/vagrant/sites-available/submitty.conf /etc/apache2/sites-available/submitty.conf
        # cp ${SUBMITTY_REPOSITORY}/.setup/vagrant/sites-available/git.conf      /etc/apache2/sites-available/git.conf

        sed -i -e "s/SUBMITTY_URL/${SUBMISSION_URL:7}/g" /etc/apache2/sites-available/submitty.conf

        # permissions: rw- r-- ---
        chmod 0640 /etc/apache2/sites-available/*.conf
        a2ensite submitty
        # a2ensite git

        sed -i '25s/^/\#/' /etc/pam.d/common-password
    	sed -i '26s/pam_unix.so obscure use_authtok try_first_pass sha512/pam_unix.so obscure minlen=1 sha512/' /etc/pam.d/common-password

        # Enable xdebug support for debugging
        phpenmod xdebug

        # In case you reprovision without wiping the drive, don't paste this twice
        if [ -z $(grep 'xdebug\.remote_enable' /etc/php/${PHP_VERSION}/mods-available/xdebug.ini) ]
        then
            # Tell it to send requests to our host on port 9000 (PhpStorm default)
            cat << EOF >> /etc/php/${PHP_VERSION}/mods-available/xdebug.ini
[xdebug]
xdebug.remote_enable=1
xdebug.remote_port=9000
xdebug.remote_host=10.0.2.2
EOF
        fi

        if [ -z $(grep 'xdebug\.profiler_enable_trigger' /etc/php/${PHP_VERSION}/mods-available/xdebug.ini) ]
        then
            # Allow remote profiling and upload outputs to the shared folder
            cat << EOF >> /etc/php/${PHP_VERSION}/mods-available/xdebug.ini
xdebug.profiler_enable_trigger=1
xdebug.profiler_output_dir=${SUBMITTY_REPOSITORY}/.vagrant/Ubuntu/profiler
EOF
        fi
    fi

    cp ${SUBMITTY_REPOSITORY}/.setup/php-fpm/pool.d/submitty.conf /etc/php/${PHP_VERSION}/fpm/pool.d/submitty.conf
    cp ${SUBMITTY_REPOSITORY}/.setup/apache/www-data /etc/apache2/suexec/www-data
    chmod 0640 /etc/apache2/suexec/www-data

    #################################################################
    # PHP SETUP
    #################

    # Edit php settings.  Note that if you need to accept larger files,
    # you’ll need to increase both upload_max_filesize and
    # post_max_filesize

    sed -i -e 's/^max_execution_time = 30/max_execution_time = 60/g' /etc/php/${PHP_VERSION}/fpm/php.ini
    sed -i -e 's/^upload_max_filesize = 2M/upload_max_filesize = 10M/g' /etc/php/${PHP_VERSION}/fpm/php.ini
    sed -i -e 's/^session.gc_maxlifetime = 1440/session.gc_maxlifetime = 86400/' /etc/php/${PHP_VERSION}/fpm/php.ini
    sed -i -e 's/^post_max_size = 8M/post_max_size = 10M/g' /etc/php/${PHP_VERSION}/fpm/php.ini
    sed -i -e 's/^allow_url_fopen = On/allow_url_fopen = Off/g' /etc/php/${PHP_VERSION}/fpm/php.ini
    sed -i -e 's/^session.cookie_httponly =/session.cookie_httponly = 1/g' /etc/php/${PHP_VERSION}/fpm/php.ini
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

    sed -i -e "s/^disable_functions = .*/disable_functions = ${DISABLED_FUNCTIONS}/g" /etc/php/${PHP_VERSION}/fpm/php.ini
fi

# create directories and fix permissions
mkdir -p ${SUBMITTY_DATA_DIR}

#Set up database and copy down the tutorial repo if not in worker mode
if [ ${WORKER} == 0 ]; then
    # create a list of valid userids and put them in /var/local/submitty/instructors
    # one way to create your list is by listing all of the userids in /home
    mkdir -p ${SUBMITTY_DATA_DIR}/instructors
    ls /home | sort > ${SUBMITTY_DATA_DIR}/instructors/valid

    #################################################################
    # POSTGRES SETUP
    #################
    if [ ${VAGRANT} == 1 ]; then
        PG_VERSION="$(psql -V | grep -m 1 -o -E '[0-9]{1,}.[0-9]{1,}' | head -1)"
        if [ ! -d "/etc/postgresql/${PG_VERSION}" ]; then
            # PG 10.x stopped putting the minor version in the folder name
            PG_VERSION="$(psql -V | grep -m 1 -o -E '[0-9]{1,}' | head -1)"
        fi
        cp /etc/postgresql/${PG_VERSION}/main/pg_hba.conf /etc/postgresql/${PG_VERSION}/main/pg_hba.conf.backup
        cp ${SUBMITTY_REPOSITORY}/.setup/vagrant/pg_hba.conf /etc/postgresql/${PG_VERSION}/main/pg_hba.conf
        echo "Creating PostgreSQL users"
        su postgres -c "source ${SUBMITTY_REPOSITORY}/.setup/vagrant/db_users.sh";
        echo "Finished creating PostgreSQL users"

        sed -i "s/#listen_addresses = 'localhost'/listen_addresses = '*'/" "/etc/postgresql/${PG_VERSION}/main/postgresql.conf"
        service postgresql restart
    fi
fi



#################################################################
# CLONE OR UPDATE THE HELPER SUBMITTY CODE REPOSITORIES
#################

/bin/bash ${SUBMITTY_REPOSITORY}/.setup/bin/update_repos.sh

if [ $? -eq 1 ]; then
    echo -n "\nERROR: FAILURE TO CLONE OR UPDATE SUBMITTY HELPER REPOSITORIES\n"
    echo -n "Exiting install_system.sh"
    exit 1
fi


#################################################################
# BUILD CLANG SETUP
#################

# NOTE: These variables must match the same variables in INSTALL_SUBMITTY_HELPER.sh
clangsrc=${SUBMITTY_INSTALL_DIR}/clang-llvm/src
clangbuild=${SUBMITTY_INSTALL_DIR}/clang-llvm/build
# note, we are not running 'ninja install', so this path is unused.
clanginstall=${SUBMITTY_INSTALL_DIR}/clang-llvm/install
 
# skip if this is a re-run
if [ ! -d "${clangsrc}" ]; then
    echo 'GOING TO PREPARE CLANG INSTALLATION FOR STATIC ANALYSIS'

    mkdir -p ${clangsrc}

    # checkout the clang sources
    git clone --depth 1 http://llvm.org/git/llvm.git ${clangsrc}/llvm
    git clone --depth 1 http://llvm.org/git/clang.git ${clangsrc}/llvm/tools/clang
    git clone --depth 1 http://llvm.org/git/clang-tools-extra.git ${clangsrc}/llvm/tools/clang/tools/extra/

    # initial cmake for llvm tools (might take a bit of time)
    mkdir -p ${clangbuild}
    pushd ${clangbuild}
    cmake -G Ninja ../src/llvm -DCMAKE_INSTALL_PREFIX=${clanginstall} -DCMAKE_BUILD_TYPE=Release -DLLVM_TARGETS_TO_BUILD=X86 -DCMAKE_C_COMPILER=/usr/bin/clang -DCMAKE_CXX_COMPILER=/usr/bin/clang++
    popd > /dev/null

    # add build targets for our tools (src to be installed in INSTALL_SUBMITTY_HELPER.sh)
    echo 'add_subdirectory(ASTMatcher)' >> ${clangsrc}/llvm/tools/clang/tools/extra/CMakeLists.txt
    echo 'add_subdirectory(UnionTool)'  >> ${clangsrc}/llvm/tools/clang/tools/extra/CMakeLists.txt

    echo 'DONE PREPARING CLANG INSTALLATION'
fi
    
#################################################################
# SUBMITTY SETUP
#################
echo Beginning Submitty Setup

#If in worker mode, run configure with --worker option.
if [ ${WORKER} == 1 ]; then
    echo  Running configure submitty in worker mode
    python3 ${SUBMITTY_REPOSITORY}/.setup/CONFIGURE_SUBMITTY.py --worker
else
    if [ ${VAGRANT} == 1 ]; then
    # This should be set by setup_distro.sh for whatever distro we have, but
    # in case it is not, default to our primary URL
    if [ -z "${SUBMISSION_URL}" ]; then
        SUBMISSION_URL='http://192.168.56.101'
    fi
    echo -e "/var/run/postgresql
    ${DB_USER}
    ${DATABASE_PASSWORD}
    America/New_York
    ${SUBMISSION_URL}


    1" | python3 ${SUBMITTY_REPOSITORY}/.setup/CONFIGURE_SUBMITTY.py --debug

    else
        python3 ${SUBMITTY_REPOSITORY}/.setup/CONFIGURE_SUBMITTY.py
    fi
fi

if [ ${WORKER} == 1 ]; then
   #Add the submitty user to /etc/sudoers if in worker mode.
    SUPERVISOR_USER=$(jq -r '.supervisor_user' ${SUBMITTY_INSTALL_DIR}/config/submitty_users.json)
    if ! grep -q "${SUPERVISOR_USER}" /etc/sudoers; then
        echo "" >> /etc/sudoers
        echo "#grant the submitty user on this worker machine access to install submitty" >> /etc/sudoers
        echo "%${SUPERVISOR_USER} ALL = (root) NOPASSWD: ${SUBMITTY_INSTALL_DIR}/.setup/INSTALL_SUBMITTY.sh" >> /etc/sudoers
        echo "#grant the submitty user on this worker machine access to the systemctl wrapper" >> /etc/sudoers
        echo "%${SUPERVISOR_USER} ALL = (root) NOPASSWD: ${SUBMITTY_INSTALL_DIR}/sbin/shipper_utils/systemctl_wrapper.py" >> /etc/sudoers
    fi
fi

# Create and setup database for non-workers
if [ ${WORKER} == 0 ]; then
    dbuser_password=`cat ${SUBMITTY_INSTALL_DIR}/.setup/submitty_conf.json | jq .database_password | tr -d '"'`

    # create the submitty_dbuser role in postgres (if it does not yet exist
    su postgres -c "psql -c \"DO \\\$do\\\$ BEGIN IF NOT EXISTS ( SELECT FROM  pg_catalog.pg_roles WHERE  rolname = '${DB_USER}') THEN  CREATE ROLE ${DB_USER} LOGIN PASSWORD '${dbuser_password}'; END IF; END \\\$do\\\$;\""

    # check to see if a submitty master database exists
    DB_EXISTS=`su -c 'psql -lqt | cut -d \| -f 1 | grep -w submitty || true' postgres`

    if [ "$DB_EXISTS" == "" ]; then
	echo "Submitty master database does not yet exist"
	PGPASSWORD=${dbuser_password} psql -d postgres -h localhost -U ${DB_USER} -c "CREATE DATABASE submitty;"
	python3 ${SUBMITTY_REPOSITORY}/migration/migrator.py -e master -e system migrate --initial
    else
	echo "Submitty master database already exists"
    fi


fi

echo Beginning Install Submitty Script
bash ${SUBMITTY_INSTALL_DIR}/.setup/INSTALL_SUBMITTY.sh clean


# (re)start the submitty grading scheduler daemon
systemctl restart submitty_autograding_shipper
systemctl restart submitty_autograding_worker
systemctl restart submitty_daemon_jobs_handler
# also, set it to automatically start on boot
systemctl enable submitty_autograding_shipper
systemctl enable submitty_autograding_worker
systemctl enable submitty_daemon_jobs_handler

#Setup website authentication if not in worker mode.
if [ ${WORKER} == 0 ]; then
    sudo mkdir -p /usr/lib/cgi-bin
    sudo chown -R www-data:www-data /usr/lib/cgi-bin

    apache2ctl -t

    if ! grep -q "${COURSE_BUILDERS_GROUP}" /etc/sudoers; then
        echo "" >> /etc/sudoers
        echo "#grant limited sudo access to members of the ${COURSE_BUILDERS_GROUP} group (instructors)" >> /etc/sudoers
        echo "%${COURSE_BUILDERS_GROUP} ALL=(ALL:ALL) ${SUBMITTY_INSTALL_DIR}/bin/generate_repos.py" >> /etc/sudoers
        echo "%${COURSE_BUILDERS_GROUP} ALL=(ALL:ALL) ${SUBMITTY_INSTALL_DIR}/bin/grading_done.py" >> /etc/sudoers
        echo "%${COURSE_BUILDERS_GROUP} ALL=(ALL:ALL) ${SUBMITTY_INSTALL_DIR}/bin/regrade.py" >> /etc/sudoers
    fi

fi

if [ ${WORKER} == 0 ]; then
    if [[ ${VAGRANT} == 1 ]]; then
        # Disable OPCache for development purposes as we don't care about the efficiency as much
        echo "opcache.enable=0" >> /etc/php/${PHP_VERSION}/fpm/conf.d/10-opcache.ini

        DISTRO=$(lsb_release -si | tr '[:upper:]' '[:lower:]')
        VERSION=$(lsb_release -sc | tr '[:upper:]' '[:lower:]')

        rm -rf ${SUBMITTY_DATA_DIR}/logs/*
        rm -rf ${SUBMITTY_REPOSITORY}/.vagrant/${DISTRO}/${VERSION}/logs/submitty
        mkdir -p ${SUBMITTY_REPOSITORY}/.vagrant/${DISTRO}/${VERSION}/logs/submitty
        mkdir -p ${SUBMITTY_REPOSITORY}/.vagrant/${DISTRO}/${VERSION}/logs/submitty/autograding
        ln -s ${SUBMITTY_REPOSITORY}/.vagrant/${DISTRO}/${VERSION}/logs/submitty/autograding ${SUBMITTY_DATA_DIR}/logs/autograding
        chown ${DAEMON_USER}:${COURSE_BUILDERS_GROUP} ${SUBMITTY_REPOSITORY}/.vagrant/${DISTRO}/${VERSION}/logs/submitty/autograding
        chown ${DAEMON_USER}:${COURSE_BUILDERS_GROUP} ${SUBMITTY_DATA_DIR}/logs/autograding
        chmod 770 ${SUBMITTY_DATA_DIR}/logs/autograding

        mkdir -p ${SUBMITTY_REPOSITORY}/.vagrant/${DISTRO}/${VERSION}/logs/submitty/access
        mkdir -p ${SUBMITTY_REPOSITORY}/.vagrant/${DISTRO}/${VERSION}/logs/submitty/site_errors
        ln -s ${SUBMITTY_REPOSITORY}/.vagrant/${DISTRO}/${VERSION}/logs/submitty/access ${SUBMITTY_DATA_DIR}/logs/access
        ln -s ${SUBMITTY_REPOSITORY}/.vagrant/${DISTRO}/${VERSION}/logs/submitty/site_errors ${SUBMITTY_DATA_DIR}/logs/site_errors
        chown -R ${PHP_USER}:${COURSE_BUILDERS_GROUP} ${SUBMITTY_DATA_DIR}/logs/access
        chmod -R 770 ${SUBMITTY_DATA_DIR}/logs/access
        chown -R ${PHP_USER}:${COURSE_BUILDERS_GROUP} ${SUBMITTY_DATA_DIR}/logs/site_errors
        chmod -R 770 ${SUBMITTY_DATA_DIR}/logs/site_errors

        # Call helper script that makes the courses and refreshes the database
        if [ ${NO_SUBMISSIONS} == 1 ]; then
            python3 ${SUBMITTY_REPOSITORY}/.setup/bin/setup_sample_courses.py --no_submissions --submission_url ${SUBMISSION_URL}
        else
            python3 ${SUBMITTY_REPOSITORY}/.setup/bin/setup_sample_courses.py --submission_url ${SUBMISSION_URL}
        fi
        #################################################################
        # SET CSV FIELDS (for classlist upload data)
        #################
        # Vagrant auto-settings are based on Rensselaer Polytechnic Institute School
        # of Science 2015-2016.

        # Other Universities will need to rerun /bin/setcsvfields to match their
        # classlist csv data.  See wiki for details.
        ${SUBMITTY_INSTALL_DIR}/sbin/setcsvfields.py 13 12 15 7
    fi
fi

#################################################################
# DOCKER SETUP
#################

# WIP: creates basic container for grading CS1 & DS assignments
# CAUTION: needs users/groups for security 
# These commands should be run manually if testing Docker integration

rm -rf /tmp/docker
mkdir -p /tmp/docker
cp ${SUBMITTY_REPOSITORY}/.setup/Dockerfile /tmp/docker/Dockerfile
cp -R ${SUBMITTY_INSTALL_DIR}/drmemory/ /tmp/docker/
cp -R ${SUBMITTY_INSTALL_DIR}/SubmittyAnalysisTools /tmp/docker/

chown ${DAEMON_USER}:${DAEMON_GROUP} -R /tmp/docker

pushd /tmp/docker
su -c 'docker build --network=host -t ubuntu:custom -f Dockerfile .' ${DAEMON_USER}
popd > /dev/null


#################################################################
# RESTART SERVICES
###################
if [ ${WORKER} == 0 ]; then
    service apache2 restart
    service php${PHP_VERSION}-fpm restart
    service postgresql restart
fi

echo "Done."
exit 0
