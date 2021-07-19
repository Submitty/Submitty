#!/usr/bin/env bash

# this script must be run by root or sudo
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit 1
fi

set -ev

# bash -c 'echo -e "#%PAM-1.0
# auth required pam_unix.so
# account required pam_unix.so" > /etc/pam.d/httpd'
# sed -i '25s/^/\#/' /etc/pam.d/common-password
# sed -i '26s/pam_unix.so obscure use_authtok try_first_pass sha512/pam_unix.so obscure minlen=1 sha512/' /etc/pam.d/common-password

echo 'in testing/setup.sh, going to make data dir ' ${SUBMITTY_DATA_DIR} ', install-dir ' $SUBMITTY_INSTALL_DIR

mkdir -p ${SUBMITTY_INSTALL_DIR}
mkdir -p ${SUBMITTY_DATA_DIR}/courses
mkdir -p ${SUBMITTY_INSTALL_DIR}/GIT_CHECKOUT

python3 $SUBMITTY_REPOSITORY/.setup/bin/create_untrusted_users.py

addgroup submitty_daemonphp
addgroup submitty_daemoncgi
addgroup submitty_course_builders
adduser ${PHP_USER} --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
adduser ${CGI_USER} --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
adduser ${CGI_USER} ${PHP_GROUP}
adduser ${PHP_USER} shadow
adduser ${CGI_USER} shadow
adduser submitty_daemon --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
adduser ${PHP_USER} submitty_daemonphp
adduser submitty_daemon submitty_daemonphp
adduser ${CGI_USER} submitty_daemoncgi
adduser submitty_daemon submitty_daemoncgi
adduser submitty_daemon docker
useradd -p $(openssl passwd -1 submitty_dbuser) submitty_dbuser

chown ${PHP_USER}:${PHP_GROUP} ${SUBMITTY_INSTALL_DIR}
chown ${PHP_USER}:${PHP_GROUP} ${SUBMITTY_DATA_DIR}
chmod -R 777 ${SUBMITTY_INSTALL_DIR}
chmod -R 777 ${SUBMITTY_DATA_DIR}

echo -e "localhost
5432
submitty_dbuser
submitty_dbuser
America/New_York
http://localhost


sysadmin@example.com
https://example.com
1


y


submitty@vagrant
do-not-reply@vagrant
localhost
25
" | python3 ${SUBMITTY_REPOSITORY}/.setup/CONFIGURE_SUBMITTY.py --debug --setup-for-sample-courses --install-dir $SUBMITTY_INSTALL_DIR --data-dir $SUBMITTY_DATA_DIR

bash -c "echo 'export PATH=${PATH}' >> /home/${PHP_USER}/.profile"
bash -c "echo 'export PATH=${PATH}' >> /home/${PHP_USER}/.bashrc"
bash -c "echo 'export PATH=${PATH}' >> /home/${DAEMON_USER}/.bashrc"
bash -c "echo 'export PATH=${PATH}' >> /home/${DAEMON_USER}/.bashrc"

python3 ${SUBMITTY_REPOSITORY}/migration/run_migrator.py -e master -e system migrate --initial

bash ${SUBMITTY_INSTALL_DIR}/.setup/INSTALL_SUBMITTY.sh clean skip_web_restart

systemctl start submitty_autograding_shipper
systemctl start submitty_autograding_worker
systemctl start submitty_websocket_server

echo 'Finished setup.'
