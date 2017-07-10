#!/bin/bash

########################################################################################################################
########################################################################################################################
# this script must be run by root or sudo
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit
fi

echo -e "\nWelcome to the Submitty Homework Submission Server Configuration\n"

echo "What is the database host? (ex: localhost or your.database.server)"
read DATABASE_HOST

echo "What is the database user? (ex: hsdbu)"
read DATABASE_USER

echo "What is the database password for the database user $DATABASE_USER?"
read DATABASE_PASSWORD

echo "What is the url for submission? (ex: http://192.168.56.101/ or https://submitty.cs.rpi.edu/)"
read SUBMISSION_URL

TAGRADING_URL=${SUBMISSION_URL}/hwgrading/
CGI_URL=${SUBMISSION_URL}/cgi-bin/

DEBUGGING_ENABLED=false

echo "Would you like to enable debugging? (NOT RECOMMENDED FOR LIVE INSTALLATION) yes/no"
read en_debug
if [ "$en_debug" = "yes" ] ; then
	DEBUGGING_ENABLED=true
fi


########################################################################################################################
########################################################################################################################

# determine location of SUBMITTY GIT repository
# this script (CONFIGURE.sh) is in the top level directory of the repository
# (this command works even if we run configure from a different directory)
SETUP_SCRIPT_DIRECTORY=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
SUBMITTY_REPOSITORY=`dirname $SETUP_SCRIPT_DIRECTORY`

# recommended (default) directory locations
SUBMITTY_INSTALL_DIR=/usr/local/submitty
SUBMITTY_DATA_DIR=/var/local/submitty

# Log locations
TAGRADING_LOG_PATH=$SUBMITTY_DATA_DIR/logs/site/
AUTOGRADING_LOG_PATH=$SUBMITTY_DATA_DIR/logs/autograding/

# recommended names for special users & groups related to the SUBMITTY system
HWPHP_USER=hwphp
HWCGI_USER=hwcgi
HWCRON_USER=hwcron
HWCRONPHP_GROUP=hwcronphp
COURSE_BUILDERS_GROUP=course_builders

# This value must be at least 60: assumed in INSTALL_SUBMITTY.sh generation of crontab
NUM_UNTRUSTED=60
# FIXME: should check for existence of these users
FIRST_UNTRUSTED_UID=$(id -u untrusted00) # untrusted's user id
FIRST_UNTRUSTED_GID=$(id -g untrusted00) # untrusted's group id

HWCRON_UID=$(id -u ${HWCRON_USER})       # hwcron's user id
HWCRON_GID=$(id -g ${HWCRON_USER})       # hwcron's group id
HWPHP_UID=$(id -u ${HWPHP_USER})         # hwphp's user id
HWPHP_GID=$(id -g ${HWPHP_USER})         # hwphp's group id
HWCGI_UID=$(id -u ${HWCGI_USER})
HWCGI_GID=$(id -g ${HWCGI_USER})

# adjust this number depending on the # of processors
# available on your hardware
MAX_INSTANCES_OF_GRADE_STUDENTS=15

# if queue is empty, wait this long before checking the queue again
GRADE_STUDENTS_IDLE_SECONDS=5
# each grade_students.sh process should idle for this long total
# before terminating the process
GRADE_STUDENTS_IDLE_TOTAL_MINUTES=16

# how often should the cron job launch a new grade_students.sh script?
# 4 starts per hour  = every 15 minutes
# 12 starts per hour = every 5 minutes
# 15 starts per hour = every 4 minutes
#GRADE_STUDENTS_STARTS_PER_HOUR=12
GRADE_STUDENTS_STARTS_PER_HOUR=20

########################################################################################################################
########################################################################################################################

# confirm that the uid/gid of the untrusted users are sequential
which_untrusted=0
while [ $which_untrusted -lt $NUM_UNTRUSTED ]; do
    an_untrusted_user=$(printf "untrusted%.2d" $which_untrusted)
    if [ $(id -u $an_untrusted_user) -ne $(($FIRST_UNTRUSTED_UID+$which_untrusted)) ] ; then
		echo "CONFIGURATION ERROR: untrusted UID not sequential: $an_untrusted_user"
		exit
	fi
    if [ $(id -g $an_untrusted_user) -ne $(($FIRST_UNTRUSTED_GID+$which_untrusted)) ] ; then
		echo "CONFIGURATION ERROR: untrusted GID not sequential: $an_untrusted_user"
	    echo "AN UNTRUSTED $an_untrusted_user"
		exit
    fi
    let which_untrusted=which_untrusted+1
done

########################################################################################################################
########################################################################################################################

# FIXME: DO SOME ERROR CHECKING ON THE VARIABLE SETTINGS
#        (variables are different from each other, directories valid/exist/writeable, etc)

# make the installation setup directory
rm -rf $SUBMITTY_INSTALL_DIR/.setup
mkdir -p $SUBMITTY_INSTALL_DIR/.setup
chown root:root $SUBMITTY_INSTALL_DIR/.setup
chmod 700 $SUBMITTY_INSTALL_DIR/.setup


########################################################################################################################
########################################################################################################################

# WRITE THE VARIABLES TO A FILE

CONFIGURATION_FILE=${SUBMITTY_INSTALL_DIR}/.setup/INSTALL_SUBMITTY.sh

echo "#!/bin/bash"                                                                            >  $CONFIGURATION_FILE
echo ""                                                                                       >> $CONFIGURATION_FILE

echo "# Variables prepared by CONFIGURE_SUBMITTY.sh"                                          >> $CONFIGURATION_FILE
echo "# Manual editing is allowed (but will be clobbered if CONFIGURE_SUBMITTY.sh is re-run)" >> $CONFIGURATION_FILE
echo ""                                                                                       >> $CONFIGURATION_FILE

echo "SUBMITTY_REPOSITORY="${SUBMITTY_REPOSITORY}                                             >> $CONFIGURATION_FILE
echo "SUBMITTY_INSTALL_DIR="${SUBMITTY_INSTALL_DIR}                                           >> $CONFIGURATION_FILE
echo "SUBMITTY_TUTORIAL_DIR="${SUBMITTY_INSTALL_DIR}/GIT_CHECKOUT_Tutorial                    >> $CONFIGURATION_FILE
echo "SUBMITTY_DATA_DIR="${SUBMITTY_DATA_DIR}                                                 >> $CONFIGURATION_FILE
echo "HWPHP_USER="${HWPHP_USER}                                                               >> $CONFIGURATION_FILE
echo "HWCGI_USER="${HWCGI_USER}                                                               >> $CONFIGURATION_FILE
echo "HWCRON_USER="${HWCRON_USER}                                                             >> $CONFIGURATION_FILE
echo "HWCRONPHP_GROUP="${HWCRONPHP_GROUP}                                                     >> $CONFIGURATION_FILE
echo "COURSE_BUILDERS_GROUP="${COURSE_BUILDERS_GROUP}                                         >> $CONFIGURATION_FILE

echo "NUM_UNTRUSTED="${NUM_UNTRUSTED}                                                         >> $CONFIGURATION_FILE
echo "FIRST_UNTRUSTED_UID="${FIRST_UNTRUSTED_UID}                                             >> $CONFIGURATION_FILE
echo "FIRST_UNTRUSTED_GID="${FIRST_UNTRUSTED_GID}                                             >> $CONFIGURATION_FILE

echo "HWCRON_UID="${HWCRON_UID}                                                               >> $CONFIGURATION_FILE
echo "HWCRON_GID="${HWCRON_GID}                                                               >> $CONFIGURATION_FILE
echo "HWPHP_UID="${HWPHP_UID}                                                                 >> $CONFIGURATION_FILE
echo "HWPHP_GID="${HWPHP_GID}                                                                 >> $CONFIGURATION_FILE

echo "DATABASE_HOST="${DATABASE_HOST}                                                         >> $CONFIGURATION_FILE
echo "DATABASE_USER="${DATABASE_USER}                                                         >> $CONFIGURATION_FILE
echo "DATABASE_PASSWORD="${DATABASE_PASSWORD}                                                 >> $CONFIGURATION_FILE

echo "TAGRADING_URL="${TAGRADING_URL}                                                         >> $CONFIGURATION_FILE
echo "SUBMISSION_URL="${SUBMISSION_URL}                                                       >> $CONFIGURATION_FILE
echo "CGI_URL="${CGI_URL}                                                                     >> $CONFIGURATION_FILE
echo "SITE_LOG_PATH="${TAGRADING_LOG_PATH}                                                    >> $CONFIGURATION_FILE

echo "AUTHENTICATION_METHOD=PamAuthentication"                                                >> $CONFIGURATION_FILE
echo "DEBUGGING_ENABLED="${DEBUGGING_ENABLED}                                                 >> $CONFIGURATION_FILE
echo "AUTOGRADING_LOG_PATH="${AUTOGRADING_LOG_PATH}                                           >> $CONFIGURATION_FILE

echo "MAX_INSTANCES_OF_GRADE_STUDENTS="${MAX_INSTANCES_OF_GRADE_STUDENTS}                     >> $CONFIGURATION_FILE
echo "GRADE_STUDENTS_IDLE_SECONDS="${GRADE_STUDENTS_IDLE_SECONDS}                             >> $CONFIGURATION_FILE
echo "GRADE_STUDENTS_IDLE_TOTAL_MINUTES="${GRADE_STUDENTS_IDLE_TOTAL_MINUTES}                 >> $CONFIGURATION_FILE
echo "GRADE_STUDENTS_STARTS_PER_HOUR="${GRADE_STUDENTS_STARTS_PER_HOUR}                       >> $CONFIGURATION_FILE

echo ""                                                                                       >> $CONFIGURATION_FILE
echo "# Now actually run the installation script"                                             >> $CONFIGURATION_FILE
echo "source ${SUBMITTY_REPOSITORY}/.setup/INSTALL_SUBMITTY_HELPER.sh  \"\$@\""               >> $CONFIGURATION_FILE

chmod u+x ${CONFIGURATION_FILE}
chmod g-r ${CONFIGURATION_FILE}


########################################################################################################################
########################################################################################################################

echo -e "Configuration completed.  Now you may run the installation script"
echo -e "   sudo ${CONFIGURATION_FILE}"
echo -e "          or"
echo -e "   sudo ${CONFIGURATION_FILE} clean\n\n"
