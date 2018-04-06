#!/usr/bin/env bash

########################################################################################################################
########################################################################################################################
# COPY VARIOUS SCRIPTS USED BY INSTRUCTORS AND SYS ADMINS FOR COURSE ADMINISTRATION

echo -e "Copy the non-root scripts"

if [ -z ${SUBMITTY_INSTALL_DIR+x} ]; then
    # constants are not initialized,
    CONF_DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"/../../config
    SUBMITTY_REPOSITORY=$(jq -r '.submitty_repository' ${CONF_DIR}/submitty.json)
    SUBMITTY_INSTALL_DIR=$(jq -r '.submitty_install_dir' ${CONF_DIR}/submitty.json)
    HWCRON_USER=$(jq -r '.hwcron_user' ${CONF_DIR}/submitty_users.json)
    COURSE_BUILDERS_GROUP=$(jq -r '.course_builders_group' ${CONF_DIR}/submitty_users.json)
fi

# make the directory (has a different name)
mkdir -p ${SUBMITTY_INSTALL_DIR}/bin
chown root:${COURSE_BUILDERS_GROUP} ${SUBMITTY_INSTALL_DIR}/bin
chmod 755 ${SUBMITTY_INSTALL_DIR}/bin

mkdir -p ${SUBMITTY_INSTALL_DIR}/bin/autograder
chown root:${HWCRON_USER} ${SUBMITTY_INSTALL_DIR}/bin/autograder
chmod 755 ${SUBMITTY_INSTALL_DIR}/bin/autograder

# copy all of the files
rsync -rtz  ${SUBMITTY_REPOSITORY}/bin/*   ${SUBMITTY_INSTALL_DIR}/bin/

# www-data needs to have access to this so that it can authenticate for git
chown root:www-data ${SUBMITTY_INSTALL_DIR}/bin/authentication.py
chmod 550 ${SUBMITTY_INSTALL_DIR}/bin/authentication.py

# all course builders (instructors & head TAs) need read/execute access to these scripts
array=( regrade.py grading_done.py read_iclicker_ids.py left_right_parse.py)
for i in "${array[@]}"; do
    chown root:${COURSE_BUILDERS_GROUP} ${SUBMITTY_INSTALL_DIR}/bin/${i}
    chmod 550 ${SUBMITTY_INSTALL_DIR}/bin/${i}
done

# everyone needs to run this script
chmod 555 ${SUBMITTY_INSTALL_DIR}/bin/killall.py

# course builders & hwcron need access to these scripts
array=( build_homework_function.sh make_assignments_txt_file.py )
for i in "${array[@]}"; do
    chown ${HWCRON_USER}:${COURSE_BUILDERS_GROUP} ${SUBMITTY_INSTALL_DIR}/bin/${i}
    chmod 550 ${SUBMITTY_INSTALL_DIR}/bin/${i}
done

# hwcron only things
array=( submitty_autograding_shipper.py submitty_autograding_worker.py build_config_upload.py )
for i in "${array[@]}"; do
    chown root:${HWCRON_USER} ${SUBMITTY_INSTALL_DIR}/bin/${i}
    chmod 550 ${SUBMITTY_INSTALL_DIR}/bin/${i}
done

array=( insert_database_version_data.py grade_item.py grade_items_logging.py write_grade_history.py )
for i in "${array[@]}"; do
    chown root:${HWCRON_USER} ${SUBMITTY_INSTALL_DIR}/bin/autograder/${i}
    chmod 550 ${SUBMITTY_INSTALL_DIR}/bin/autograder/${i}
done

#####################################

echo -e "Copy the root scripts"

# make the directory (has a different name)
mkdir -p ${SUBMITTY_INSTALL_DIR}/sbin
chown root:root ${SUBMITTY_INSTALL_DIR}/sbin
chmod 755 ${SUBMITTY_INSTALL_DIR}/sbin

# copy all of the files
rsync -rtz  ${SUBMITTY_REPOSITORY}/sbin/*   ${SUBMITTY_INSTALL_DIR}/sbin/

# the scripts should be root only
find ${SUBMITTY_INSTALL_DIR}/sbin -type f -exec chown root:root {} \;
find ${SUBMITTY_INSTALL_DIR}/sbin -type f -exec chmod 500 {} \;
