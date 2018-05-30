#!/usr/bin/env bash

########################################################################################################################
########################################################################################################################
# COPY VARIOUS SCRIPTS USED BY INSTRUCTORS AND SYS ADMINS FOR COURSE ADMINISTRATION

echo -e "Copy the user scripts"

if [ -z ${SUBMITTY_INSTALL_DIR+x} ]; then
    # constants are not initialized,
    CONF_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"/../../config
    SUBMITTY_REPOSITORY=$(jq -r '.submitty_repository' ${CONF_DIR}/submitty.json)
    SUBMITTY_INSTALL_DIR=$(jq -r '.submitty_install_dir' ${CONF_DIR}/submitty.json)
    HWCRON_USER=$(jq -r '.hwcron_user' ${CONF_DIR}/submitty_users.json)
    COURSE_BUILDERS_GROUP=$(jq -r '.course_builders_group' ${CONF_DIR}/submitty_users.json)
fi

# make the directory (has a different name)
mkdir -p ${SUBMITTY_INSTALL_DIR}/bin
chown root:${COURSE_BUILDERS_GROUP} ${SUBMITTY_INSTALL_DIR}/bin
chmod 755 ${SUBMITTY_INSTALL_DIR}/bin

# copy all of the files
rsync -rtz  ${SUBMITTY_REPOSITORY}/bin/*   ${SUBMITTY_INSTALL_DIR}/bin/

# all course builders (instructors & head TAs) need read/execute access to these scripts
array=( grading_done.py left_right_parse.py read_iclicker_ids.py regrade.py )
for i in "${array[@]}"; do
    chown root:${COURSE_BUILDERS_GROUP} ${SUBMITTY_INSTALL_DIR}/bin/${i}
    chmod 550 ${SUBMITTY_INSTALL_DIR}/bin/${i}
done

# course builders & hwcron need access to these scripts
array=( build_homework_function.sh make_assignments_txt_file.py )
for i in "${array[@]}"; do
    chown ${HWCRON_USER}:${COURSE_BUILDERS_GROUP} ${SUBMITTY_INSTALL_DIR}/bin/${i}
    chmod 550 ${SUBMITTY_INSTALL_DIR}/bin/${i}
done

chown root:root ${SUBMITTY_INSTALL_DIR}/bin/generate_repos.py
chmod 500 ${SUBMITTY_INSTALL_DIR}/bin/generate_repos.py

#####################################

echo -e "Copy the non-user scripts"

# make the directory (has a different name)
mkdir -p ${SUBMITTY_INSTALL_DIR}/sbin
chown root:root ${SUBMITTY_INSTALL_DIR}/sbin
chmod 755 ${SUBMITTY_INSTALL_DIR}/sbin

mkdir -p ${SUBMITTY_INSTALL_DIR}/sbin/autograder
mkdir -p ${SUBMITTY_INSTALL_DIR}/sbin/shipper_utils

# copy all of the files
rsync -rtz  ${SUBMITTY_REPOSITORY}/sbin/*   ${SUBMITTY_INSTALL_DIR}/sbin/

# most of the scripts should be root only
find ${SUBMITTY_INSTALL_DIR}/sbin -type f -exec chown root:root {} \;
find ${SUBMITTY_INSTALL_DIR}/sbin -type f -exec chmod 500 {} \;

# www-data needs to have access to this so that it can authenticate for git
chown root:www-data ${SUBMITTY_INSTALL_DIR}/sbin/authentication.py
chmod 550 ${SUBMITTY_INSTALL_DIR}/sbin/authentication.py

# everyone needs to be able to run this script
chmod 555 ${SUBMITTY_INSTALL_DIR}/sbin/killall.py

# hwcron only things
array=( build_config_upload.py submitty_autograding_shipper.py submitty_autograding_worker.py )
for i in "${array[@]}"; do
    chown root:${HWCRON_USER} ${SUBMITTY_INSTALL_DIR}/sbin/${i}
    chmod 550 ${SUBMITTY_INSTALL_DIR}/sbin/${i}
done

chown -R root:${HWCRON_USER} ${SUBMITTY_INSTALL_DIR}/sbin/autograder
chmod 750 ${SUBMITTY_INSTALL_DIR}/sbin/autograder
chmod 550 ${SUBMITTY_INSTALL_DIR}/sbin/autograder/*

if [ "${WORKER}" == 1 ]; then
    chown -R root:${SUBMITTY_SUPERVISOR} ${SUBMITTY_INSTALL_DIR}/sbin/shipper_utils
else
    chown -R root:${HWCRON_USER} ${SUBMITTY_INSTALL_DIR}/sbin/shipper_utils
fi
chmod 750 ${SUBMITTY_INSTALL_DIR}/sbin/shipper_utils
chmod 550 ${SUBMITTY_INSTALL_DIR}/sbin/shipper_utils/*
