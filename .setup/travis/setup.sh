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

sudo mkdir -p ${SUBMITTY_INSTALL_DIR}
sudo mkdir -p ${SUBMITTY_DATA_DIR}
sudo ln -s ${TRAVIS_BUILD_DIR} ${SUBMITTY_REPOSITORY}

sudo python ${DIR}/../create_untrusted_users.py

sudo adduser ta --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
sudo echo "ta:ta" | sudo chpasswd
sudo adduser instructor --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
sudo echo "instructor:instructor" | sudo chpasswd
sudo adduser instructor sudo

sudo addgroup hwcronphp
sudo addgroup course_builders
sudo adduser hwphp --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
sudo adduser hwcgi --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
sudo adduser hwcgi hwphp
sudo adduser hwcgi shadow
sudo adduser hwcron --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
sudo adduser hwphp hwcronphp
sudo adduser hwcron hwcronphp
sudo adduser hsdbu --gecos "First Last,RoomNumber,WorkPhone,HomePhone" --disabled-password
sudo echo "hsdbu:hsdbu" | sudo chpasswd


sudo addgroup csci1000
sudo addgroup csci1000_tas_www
sudo adduser hwphp csci1000_tas_www
sudo adduser hwcron csci1000_tas_www

sudo adduser instructor course_builders
sudo adduser ta csci1000
sudo adduser ta csci1000_tas_www
sudo adduser instructor csci1000
sudo adduser instructor csci1000_tas_www

sudo chown hwphp:hwphp ${SUBMITTY_INSTALL_DIR}
sudo chown hwphp:hwphp ${SUBMITTY_DATA_DIR}
sudo chmod 777         ${SUBMITTY_INSTALL_DIR}
sudo chmod 777         ${SUBMITTY_DATA_DIR}

sudo echo -e "localhost
hsdbu
hsdbu
http://localhost
http://localhost/TAGrading
http://localhost/cgi-bin
svn+ssh:192.168.56.103" | sudo bash ${SUBMITTY_REPOSITORY}/.setup/CONFIGURE_SUBMITTY.sh

sudo bash ${SUBMITTY_INSTALL_DIR}/.setup/INSTALL_SUBMITTY.sh clean

sudo bash ${SUBMITTY_REPOSITORY}/Docs/sample_bin/admin_scripts_setup
sudo chmod 777 ${SUBMITTY_DATA_DIR}/instructors/authlist
sudo chmod 777 ${SUBMITTY_DATA_DIR}/instructors/valid
sudo echo "student" >> ${SUBMITTY_DATA_DIR}/instructors/authlist
sudo echo "student" >> ${SUBMITTY_DATA_DIR}/instructors/valid
sudo echo "smithj" >> ${SUBMITTY_DATA_DIR}/instructors/authlist
sudo echo "smithj" >> ${SUBMITTY_DATA_DIR}/instructors/valid
sudo echo "joness" >> ${SUBMITTY_DATA_DIR}/instructors/authlist
sudo echo "joness" >> ${SUBMITTY_DATA_DIR}/instructors/valid
sudo echo "browna" >> ${SUBMITTY_DATA_DIR}/instructors/authlist
sudo echo "browna" >> ${SUBMITTY_DATA_DIR}/instructors/valid
sudo echo "\n" | sudo perl ${SUBMITTY_DATA_DIR}/bin/authonly.pl
sudo echo "student:student" | sudo chpasswd
sudo echo "smithj:smithj" | sudo chpasswd
sudo echo "joness:joness" | sudo chpasswd
sudo echo "browna:browna" | sudo chpasswd
sudo python ${SUBMITTY_REPOSITORY}/.setup/add_sample_courses.py csci1000
