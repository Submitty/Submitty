#!/usr/bin/env bash


########################################################################################################################
########################################################################################################################
# COPY VARIOUS SCRIPTS USED BY INSTRUCTORS AND SYS ADMINS FOR COURSE ADMINISTRATION

echo -e "Copy the user scripts"

CONF_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"/../../../../config
SUBMITTY_REPOSITORY=$(jq -r '.submitty_repository' ${CONF_DIR}/submitty.json)
SUBMITTY_INSTALL_DIR=$(jq -r '.submitty_install_dir' ${CONF_DIR}/submitty.json)
COURSE_BUILDERS_GROUP=$(jq -r '.course_builders_group' ${CONF_DIR}/submitty_users.json)
CGI_USER=$(jq -r '.cgi_user' ${CONF_DIR}/submitty_users.json)
DAEMON_USER=$(jq -r '.daemon_user' ${CONF_DIR}/submitty_users.json)
DAEMON_GROUP=${DAEMON_USER}
SUPERVISOR_USER=$(jq -r '.supervisor_user' ${CONF_DIR}/submitty_users.json)
WORKER=$([[ $(jq -r '.worker' ${CONF_DIR}/submitty.json) == "true" ]] && echo 1 || echo 0)

# make the directory (has a different name)
mkdir -p ${SUBMITTY_INSTALL_DIR}/bin
chown root:${COURSE_BUILDERS_GROUP} ${SUBMITTY_INSTALL_DIR}/bin
chmod 755 ${SUBMITTY_INSTALL_DIR}/bin

# copy all of the files
rsync -rtz  ${SUBMITTY_REPOSITORY}/bin/*   ${SUBMITTY_INSTALL_DIR}/bin/

# all course builders (instructors & head TAs) need read/execute access to these scripts
array=( grading_done.py left_right_parse.py regrade.py extract_notes_page.py )
for i in "${array[@]}"; do
    chown root:${COURSE_BUILDERS_GROUP} ${SUBMITTY_INSTALL_DIR}/bin/${i}
    chmod 550 ${SUBMITTY_INSTALL_DIR}/bin/${i}
done

# COURSE_BUILDERS & DAEMON_USER need access to these scripts
array=( build_homework_function.sh make_assignments_txt_file.py make_generated_output.py config_syntax_check.py json_schemas json_schemas/complete_config_schema.json set_allowed_mins.py comment_count.py )
for i in "${array[@]}"; do
    chown ${DAEMON_USER}:${COURSE_BUILDERS_GROUP} ${SUBMITTY_INSTALL_DIR}/bin/${i}
    chmod 550 ${SUBMITTY_INSTALL_DIR}/bin/${i}
done

chown root:root ${SUBMITTY_INSTALL_DIR}/bin/generate_repos.py
chmod 500 ${SUBMITTY_INSTALL_DIR}/bin/generate_repos.py

# others need rx permissions for comment_count because its an autograding command
chmod o+rx ${SUBMITTY_INSTALL_DIR}/bin/comment_count.py

#####################################

echo -e "Copy the non-user scripts"

# make the directory (has a different name)
mkdir -p ${SUBMITTY_INSTALL_DIR}/sbin
chown root:root ${SUBMITTY_INSTALL_DIR}/sbin
chmod 755 ${SUBMITTY_INSTALL_DIR}/sbin

mkdir -p ${SUBMITTY_INSTALL_DIR}/autograder
mkdir -p ${SUBMITTY_INSTALL_DIR}/sbin/shipper_utils

# copy all of the files
rsync -rtz --exclude tests  ${SUBMITTY_REPOSITORY}/sbin/*   ${SUBMITTY_INSTALL_DIR}/sbin/
rsync -rtz  ${SUBMITTY_REPOSITORY}/autograder/* ${SUBMITTY_INSTALL_DIR}/autograder/

# most of the scripts should be root only
find ${SUBMITTY_INSTALL_DIR}/sbin -type f -exec chown root:root {} \;
find ${SUBMITTY_INSTALL_DIR}/sbin -type f -exec chmod 500 {} \;

# www-data needs to have access to this so that it can authenticate for git
chown root:www-data ${SUBMITTY_INSTALL_DIR}/sbin/authentication.py
chmod 550 ${SUBMITTY_INSTALL_DIR}/sbin/authentication.py

# submitty_daemon needs to check for zombie networks during autograding
chown root:"${DAEMON_GROUP}" ${SUBMITTY_INSTALL_DIR}/sbin/docker_cleanup.sh
chmod 550 ${SUBMITTY_INSTALL_DIR}/sbin/docker_cleanup.sh

# everyone needs to be able to run this script
chmod 555 ${SUBMITTY_INSTALL_DIR}/sbin/killall.py

# DAEMON_USER only things in sbin
array=( auto_rainbow_grades.py auto_rainbow_scheduler.py build_config_upload.py send_email.py cleanup_old_email.py generate_grade_summaries.py submitty_daemon_jobs)
for i in "${array[@]}"; do
    chown -R root:"${DAEMON_GROUP}" ${SUBMITTY_INSTALL_DIR}/sbin/${i}
    chmod -R 750 ${SUBMITTY_INSTALL_DIR}/sbin/${i}
done

# DAEMON_USER only things in autograder
chown -R root:"${DAEMON_GROUP}" ${SUBMITTY_INSTALL_DIR}/autograder
chmod -R 750 ${SUBMITTY_INSTALL_DIR}/autograder

if [ "${WORKER}" == 1 ]; then
    chown -R ${SUPERVISOR_USER}:${SUPERVISOR_USER} ${SUBMITTY_INSTALL_DIR}/sbin/shipper_utils
else
    chown -R root:${DAEMON_GROUP} ${SUBMITTY_INSTALL_DIR}/sbin/shipper_utils
fi
chmod 750 ${SUBMITTY_INSTALL_DIR}/sbin/shipper_utils
chmod 550 ${SUBMITTY_INSTALL_DIR}/sbin/shipper_utils/*

# set the permissions here in the case we JUST run this script or else things will break
if [ -f ${SUBMITTY_INSTALL_DIR}/sbin/untrusted_execute ]; then
    chgrp ${DAEMON_GROUP}  ${SUBMITTY_INSTALL_DIR}/sbin/untrusted_execute
    chmod 4550             ${SUBMITTY_INSTALL_DIR}/sbin/untrusted_execute
fi

if [ -f ${SUBMITTY_INSTALL_DIR}/bin/system_call_check.out ]; then
    chown root:${COURSE_BUILDERS_GROUP} ${SUBMITTY_INSTALL_DIR}/bin/system_call_check.out
    chmod 550                           ${SUBMITTY_INSTALL_DIR}/bin/system_call_check.out
fi


echo -e "Updating system dependencies"
bash "${SUBMITTY_REPOSITORY}/.setup/update_system.sh"
