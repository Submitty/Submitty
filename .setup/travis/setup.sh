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

sudo mkdir -p ${SUBMITTY_INSTALL_DIR}
sudo mkdir -p ${SUBMITTY_DATA_DIR}
sudo ln -s ${TRAVIS_BUILD_DIR} ${SUBMITTY_REPOSITORY}

sudo ${DIR}/../bin/create_untrusted_users.py

sudo addgroup hwcronphp
sudo addgroup course_builders
sudo adduser hwphp --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
sudo adduser hwcgi --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
sudo adduser hwcgi hwphp
sudo adduser hwphp shadow
sudo adduser hwcgi shadow
sudo adduser hwcron --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
sudo adduser hwphp hwcronphp
sudo adduser hwcron hwcronphp
sudo adduser hsdbu --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
sudo echo "hsdbu:hsdbu" | sudo chpasswd

sudo chown hwphp:hwphp ${SUBMITTY_INSTALL_DIR}
sudo chown hwphp:hwphp ${SUBMITTY_DATA_DIR}
sudo chmod 777         ${SUBMITTY_INSTALL_DIR}
sudo chmod 777         ${SUBMITTY_DATA_DIR}

echo -e "/var/run/postgresql
hsdbu
hsdbu
http://localhost

${AUTH_METHOD}" | sudo ${SUBMITTY_REPOSITORY}/.setup/CONFIGURE_SUBMITTY.py --debug


mkdir -p ${SUBMITTY_DATA_DIR}/instructors
mkdir -p ${SUBMITTY_DATA_DIR}/bin
touch ${SUBMITTY_DATA_DIR}/instructors/authlist
touch ${SUBMITTY_DATA_DIR}/instructors/valid
[ ! -f ${SUBMITTY_DATA_DIR}/bin/authonly.pl ] && cp ${SUBMITTY_REPOSITORY}/Docs/sample_bin/authonly.pl ${SUBMITTY_DATA_DIR}/bin/authonly.pl
[ ! -f ${SUBMITTY_DATA_DIR}/bin/validate.auth.pl ] && cp ${SUBMITTY_REPOSITORY}/Docs/sample_bin/validate.auth.pl ${SUBMITTY_DATA_DIR}/bin/validate.auth.pl
chmod 660 ${SUBMITTY_DATA_DIR}/instructors/authlist
chmod 640 ${SUBMITTY_DATA_DIR}/instructors/valid

sudo bash ${SUBMITTY_INSTALL_DIR}/.setup/INSTALL_SUBMITTY.sh clean
