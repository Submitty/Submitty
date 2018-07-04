#!/bin/bash

SOURCE="${BASH_SOURCE[0]}"
# resolve $SOURCE until the file is no longer a symlink
while [ -h "$SOURCE" ]; do
  DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"
  SOURCE="$(readlink "$SOURCE")"
  # if $SOURCE was a relative symlink, we need to resolve
  # it relative to the path where the symlink file was located
  [[ ${SOURCE} != /* ]] && SOURCE="$DIR/$SOURCE"
done
DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"

source ${DIR}/../common/common_env.sh

#sudo chmod -R 755 /home/travis/build

#if [ ! -f "$SELENIUM_JAR" ]; then
#    echo "Downloading Selenium"
#    sudo mkdir -p $(dirname "${SELENIUM_JAR}")
#    sudo wget -O "${SELENIUM_JAR}" "${SELENIUM_DOWNLOAD_URL}"
#    echo "Downloaded Selenium"
#fi

sudo bash -c 'echo -e "#%PAM-1.0
auth required pam_unix.so
account required pam_unix.so" > /etc/pam.d/httpd'
sudo sed -i '25s/^/\#/' /etc/pam.d/common-password
sudo sed -i '26s/pam_unix.so obscure use_authtok try_first_pass sha512/pam_unix.so obscure minlen=1 sha512/' /etc/pam.d/common-password

echo 'in travis setup, going to make data dir ' ${SUBMITTY_DATA_DIR}

sudo mkdir -p ${SUBMITTY_INSTALL_DIR}
sudo mkdir -p ${SUBMITTY_DATA_DIR}/courses
sudo mkdir -p ${SUBMITTY_INSTALL_DIR}/GIT_CHECKOUT
sudo cp -R ${TRAVIS_BUILD_DIR} ${SUBMITTY_REPOSITORY}

sudo python3 ${DIR}/../bin/create_untrusted_users.py

sudo addgroup submitty_daemonphp
sudo addgroup submitty_course_builders
sudo adduser ${PHP_USER} --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
sudo adduser ${CGI_USER} --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
sudo adduser ${CGI_USER} ${PHP_GROUP}
sudo adduser ${PHP_USER} shadow
sudo adduser ${CGI_USER} shadow
sudo adduser submitty_daemon --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
sudo adduser ${PHP_USER} submitty_daemonphp
sudo adduser submitty_daemon submitty_daemonphp
sudo adduser submitty_dbuser --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
sudo echo "submitty_dbuser:submitty_dbuser" | sudo chpasswd

sudo chown ${PHP_USER}:${PHP_GROUP} ${SUBMITTY_INSTALL_DIR}
sudo chown ${PHP_USER}:${PHP_GROUP} ${SUBMITTY_DATA_DIR}
sudo chmod -R 777 ${SUBMITTY_INSTALL_DIR}
sudo chmod -R 777 ${SUBMITTY_DATA_DIR}

echo -e "/var/run/postgresql
submitty_dbuser
submitty_dbpass
America/New_York
http://localhost
http://localhost/git

${AUTH_METHOD}" | sudo python3 ${SUBMITTY_REPOSITORY}/.setup/CONFIGURE_SUBMITTY.py --debug

sudo bash -c "echo 'export PATH=${PATH}' >> /home/${PHP_USER}/.profile"
sudo bash -c "echo 'export PATH=${PATH}' >> /home/${PHP_USER}/.bashrc"
# necessary so that PHP_USER has access to /home/travis/.phpenv/shims/composer
sudo usermod -a -G travis ${PHP_USER}

# necessary to pass config path as submitty_repository is a symlink
sudo python3 ${SUBMITTY_REPOSITORY}/migration/migrator.py -e master -e system migrate --initial

sudo bash ${SUBMITTY_INSTALL_DIR}/.setup/INSTALL_SUBMITTY.sh clean
