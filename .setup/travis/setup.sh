#!/bin/bash

SUBMITTY_REPOSITORY=
SUBMITTY_INSTALL_DIR=
SUBMITTY_DATA_DIR=

SOURCE="${BASH_SOURCE[0]}"
while [ -h "$SOURCE" ]; do # resolve $SOURCE until the file is no longer a symlink
  DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"
  SOURCE="$(readlink "$SOURCE")"
  [[ ${SOURCE} != /* ]] && SOURCE="$DIR/$SOURCE" # if $SOURCE was a relative symlink, we need to resolve it relative to the path where the symlink file was located
done
DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"

source ${DIR}/../common/common_env.sh

#sudo chmod -R 755 /home/travis/build

if [ ! -f "$SELENIUM_JAR" ]; then
    echo "Downloading Selenium"
    sudo mkdir -p $(dirname "${SELENIUM_JAR}")
    sudo wget -O "${SELENIUM_JAR}" "${SELENIUM_DOWNLOAD_URL}"
    echo "Downloaded Selenium"
fi

sudo mkdir -p ${SUBMITTY_INSTALL_DIR}
sudo mkdir -p ${SUBMITTY_DATA_DIR}

python ${DIR}/../create_untrusted_users.py

adduser ta --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
echo "ta:ta" | sudo chpasswd
adduser instructor --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
echo "instructor:instructor" | sudo chpasswd
adduser instructor sudo

addgroup hwcronphp
addgroup course_builders
adduser hwphp --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
adduser hwcgi --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
adduser hwcgi hwphp
adduser hwcgi shadow
adduser hwcron --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
adduser hwphp hwcronphp
adduser hwcron hwcronphp

addgroup csci1000
addgroup csci1000_tas_www
adduser hwphp csci1000_tas_www
adduser hwcron csci1000_tas_www

adduser instructor course_builders
adduser ta csci1000
adduser ta csci1000_tas_www
adduser instructor csci1000
adduser	instructor csci1000_tas_www

sudo chown hwphp:hwphp ${SUBMITTY_INSTALL_DIR}
sudo chown hwphp:hwphp ${SUBMITTY_DATA_DIR}
sudo chmod 777         ${SUBMITTY_INSTALL_DIR}
sudo chmod 777         ${SUBMITTY_DATA_DIR}

echo -e "localhost
hsdbu
hsdbu
http://192.168.56.101
http://192.168.56.101/TAGrading
http://192.168.56.101/cgi-bin
svn+ssh:192.168.56.103" | source ${SUBMITTY_REPOSITORY}/.setup/CONFIGURE_SUBMITTY.sh

source ${SUBMITTY_INSTALL_DIR}/.setup/INSTALL_SUBMITTY.sh clean

source ${SUBMITTY_REPOSITORY}/Docs/sample_bin/admin_scripts_setup
echo "student" >> ${SUBMITTY_DATA_DIR}/instructors/authlist
echo "student" >> ${SUBMITTY_DATA_DIR}/instructors/valid
echo "smithj" >> ${SUBMITTY_DATA_DIR}/instructors/authlist
echo "smithj" >> ${SUBMITTY_DATA_DIR}/instructors/valid
${SUBMITTY_DATA_DIR}/bin/authonly.pl
echo "student:student" | sudo chpasswd
echo "smithj:smithj" | sudo chpasswd
python ${SUBMITTY_REPOSITORY}/.setup/add_sample_courses.py csci1100