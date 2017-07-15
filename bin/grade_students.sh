#!/bin/bash

# ======================================================================
# check user...

HWCRON_UID=__INSTALL__FILLIN__HWCRON_UID__
if [[ "$UID" -ne "$HWCRON_UID" ]] ; then
    echo "ERROR: This script must be run by hwcron"
    exit
fi

# ======================================================================
# this script takes 1 required parameter (which 'untrusted' user to use)
# and one optional parameter (must be "continuous")

if [[ "$#" -lt 1 || "$#" -gt 2 ]]; then
    echo "ERROR: Illegal number of parameters" >&2
    echo "   ./grade_students  untrusted00" >&2
    echo "   ./grade_students  untrusted00  continuous" >&2
    exit 1
fi

ARGUMENT_UNTRUSTED_USER=$1
ARGUMENT_CONTINUOUS=false

untrusted_user_length=${#ARGUMENT_UNTRUSTED_USER}
if [[ "$untrusted_user_length" -ne 11 || "${ARGUMENT_UNTRUSTED_USER:0:9}" != "untrusted" ]]; then
    echo "ERROR: Invalid untrusted user $ARGUMENT_UNTRUSTED_USER" >&2
    echo "   ./grade_students  untrusted00" >&2
    echo "   ./grade_students  untrusted00  continuous" >&2
    exit 1
fi

if [[ "$#" -eq 2 ]]; then
    if [[ $2 != "continuous" ]]; then
	echo "ERROR:  Illegal parameter  $ARGUMENT_CONTINUOUS" >&2
	echo "   ./grade_students  untrusted00" >&2
	echo "   ./grade_students  untrusted00  continuous" >&2
	exit 1
    else
	ARGUMENT_CONTINUOUS=true
    fi
fi


# ======================================================================
# these variables will be replaced by INSTALL.sh

SUBMITTY_INSTALL_DIR=__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__
SUBMITTY_DATA_DIR=__INSTALL__FILLIN__SUBMITTY_DATA_DIR__

# SVN_PATH=__INSTALL__FILLIN__SVN_PATH__

AUTOGRADING_LOG_PATH=__INSTALL__FILLIN__AUTOGRADING_LOG_PATH__

MAX_INSTANCES_OF_GRADE_STUDENTS=__INSTALL__FILLIN__MAX_INSTANCES_OF_GRADE_STUDENTS__
GRADE_STUDENTS_IDLE_SECONDS=__INSTALL__FILLIN__GRADE_STUDENTS_IDLE_SECONDS__
GRADE_STUDENTS_IDLE_TOTAL_MINUTES=__INSTALL__FILLIN__GRADE_STUDENTS_IDLE_TOTAL_MINUTES__

# =====================================================================
# The todo lists of the most recent (ungraded) submissions have a dummy
# file in one of these directories:

TO_BE_GRADED_INTERACTIVE=$SUBMITTY_DATA_DIR/to_be_graded_interactive
TO_BE_GRADED_BATCH=$SUBMITTY_DATA_DIR/to_be_graded_batch


if [ ! -d "$TO_BE_GRADED_INTERACTIVE" ]; then
    echo "ERROR: interactive to_be_graded directory $TO_BE_GRADED_INTERACTIVE does not exist" >&2
    exit 1
fi

if [ ! -d "$TO_BE_GRADED_BATCH" ]; then
    echo "ERROR: batch to_be_graded directory $TO_BE_GRADED_BATCH does not exist" >&2
    exit 1
fi



# =====================================================================
# NOTE ON OUTPUT: cron job stdout/stderr gets emailed
# debugging type output can be sent to stdout, which we'll redirect to /dev/null in the cron job
# all problematic errors should get set to stderr  (>&2)  so that an email will be sent
echo "Grade all submissions in (interactive) $TO_BE_GRADED_INTERACTIVE"
echo "                    then (batch)       $TO_BE_GRADED_BATCH"



# =====================================================================
# SETUP THE DIRECTORY LOCK FILES (TWO OF THEM!)

# arbitrarily using file descriptor 200 for the todolist
TODO_LOCK_FILE=200
exec 200>/var/lock/homework_submissions_server_todo_lockfile || exit 1

# arbitrarily using file descriptor 201 for the log file
LOG_LOCK_FILE=201
exec 201>/var/lock/homework_submissions_server_log_lockfile || exit 1



# =====================================================================
# =====================================================================
# HELPER FUNCTIONS FOR LOGGING
# =====================================================================
# =====================================================================

function log_message {

    if [[ $# != 5 ]] ;
    then
	echo "ERROR: log_message called with the wrong number of arguments"
	echo "  num arguments = $#"
	echo "  arguments = '$@'"
	exit 1
    fi

    # function arguments
    is_batch=$1
    jobname=$2
    timelabel=$3
    elapsed_time=$4
    message=$5

    # log file name
    DATE=`date +%Y%m%d`
    AUTOGRADING_LOG_FILE=$AUTOGRADING_LOG_PATH/$DATE.txt

    EASY_TO_READ_DATE=`date`

    # first lock the file to avoid conflict with other grading processes!
    flock -w 5 $LOG_LOCK_FILE || { echo "ERROR: flock() failed. $NEXT_ITEM" >&2; exit 1; }

    time_unit="sec"
    if [[ $timelabel == "" && $elapsed_time == "" ]] ;
    then
	time_unit=""
    fi

    printf "%-36s | %-6s | %5s | %-70s | %-6s %5s %3s | %s\n" \
	"$EASY_TO_READ_DATE" \
	"$BASHPID" \
	"$is_batch" \
	"$jobname" \
	"$timelabel" \
	"$elapsed_time" \
	"$time_unit" \
	"$message" \
	>> $AUTOGRADING_LOG_FILE
    flock -u $LOG_LOCK_FILE
}


function log_error {

    if [[ $# != 2 ]] ;
    then
	echo "ERROR: log_error called with the wrong number of arguments"
	echo "  num arguments = $#"
	echo "  arguments = '$@'"
	exit 1
    fi

    # function arguments
    jobname=$1
    message=$2

    log_message "" "$jobname" "" "" "ERROR: $message"

    # Also print the message to stderr (so it will be emailed from the cron job)
    echo "ERROR : $jobname : $message " >&2
}

function log_exit {

    if [[ $# != 2 ]] ;
    then
	echo "ERROR: log_exit called with the wrong number of arguments"
	echo "  num arguments = $#"
	echo "  arguments = '$@'"
	exit 1
    fi

    # function arguments
    jobname=$1
    message=$2

    log_error "$jobname" "$message"
    log_error "$jobname" "EXIT grade_students.sh"

    exit 1
}





too_many_processes_count=0
sleep_count=0



# =====================================================================
# =====================================================================
# Do some error checking

if [[ "$MAX_INSTANCES_OF_GRADE_STUDENTS" -lt 1 ||
      "$MAX_INSTANCES_OF_GRADE_STUDENTS" -ge 60 ]] ; then
    log_exit "" "Bad value for MAX_INSTANCES_OF_GRADE_STUDENTS = $MAX_INSTANCES_OF_GRADE_STUDENTS"
fi

if [[ "$GRADE_STUDENTS_IDLE_SECONDS" -lt 1 ||
      "$GRADE_STUDENTS_IDLE_SECONDS" -ge 10 ]] ; then
    log_exit "" "Bad value for GRADE_STUDENTS_IDLE_SECONDS = $GRADE_STUDENTS_IDLE_SECONDS"
fi

if [[ "$GRADE_STUDENTS_IDLE_TOTAL_MINUTES" -lt 10 ||
      "$GRADE_STUDENTS_IDLE_TOTAL_MINUTES" -gt 60 ]] ; then
    log_exit "" "Bad value for GRADE_STUDENTS_IDLE_TOTAL_MINUTES = $GRADE_STUDENTS_IDLE_TOTAL_MINUTES"
fi


# =====================================================================
# =====================================================================
# OUTER LOOP (will eventually process all submissions)
# =====================================================================
# =====================================================================

while true; do

    # -------------------------------------------------------------
    # check to see if there are any other grade_student.sh instances
    # using the same untrusted user
    specific_instances=`pgrep -f "grade_students.sh ${ARGUMENT_UNTRUSTED_USER}" -d " "`
    specific_instances_count=`echo $specific_instances | wc -w`
    echo "Running $specific_instances_count instances of grade_students.sh with argument ${ARGUMENT_UNTRUSTED_USER}"
    if [[ "$specific_instances_count" -ne 1 ]] ; then
	# This "error" may occur if the system is very busy for a long
	# period of time and the specific untrusted user is reused
	# before its previous instance completed.
	log_exit "" "Running $specific_instances_count instance(s) of grade_students.sh with argument ${ARGUMENT_UNTRUSTED_USER}, should be exactly 1"
    fi

    # -------------------------------------------------------------
    # check total number of instances of grade_students.sh
    total_instances=`pgrep -f "grade_students.sh untrusted" -d " "`
    total_instances_count=`echo $total_instances | wc -w`
    echo "Running a total of $total_instances_count instance(s) of grade_students.sh"
    if [[ "$total_instances_count" -gt ${MAX_INSTANCES_OF_GRADE_STUDENTS} ]] ; then
	log_exit "" "Running a total of $total_instances_count instances of grade_students.sh, > max allowed ${MAX_INSTANCES_OF_GRADE_STUDENTS}"
    fi


    # -------------------------------------------------------------
    # -------------------------------------------------------------
    # FIND NEXT GRADEABLE TO GRADE (in reverse chronological order)
    # -------------------------------------------------------------
    # -------------------------------------------------------------

    graded_something=false

    # Use ls in the two directories to create a list of everything in
    # the to_do list.  Prioritize grading "interative" items before all
    # "batch" items.

    interactive_list=$(ls -1rt ${TO_BE_GRADED_INTERACTIVE} | awk '{print "'${TO_BE_GRADED_INTERACTIVE}'/"$1}')
    batch_list=$(ls -1rt ${TO_BE_GRADED_BATCH} | awk '{print "'${TO_BE_GRADED_BATCH}'/"$1}')

    for NEXT_TO_GRADE in ${interactive_list} ${batch_list}; do

	NEXT_DIRECTORY=`dirname $NEXT_TO_GRADE`
	NEXT_ITEM=`basename $NEXT_TO_GRADE`


	IS_BATCH_JOB=""
	if [ "$NEXT_DIRECTORY" = "$TO_BE_GRADED_BATCH" ]
	then
	    IS_BATCH_JOB="BATCH"
	fi

	echo "please grade  "  $NEXT_DIRECTORY " : "  $NEXT_ITEM

	# -------------------------------------------------------------
	# skip the items with active grading tags
	if [ "${NEXT_ITEM:0:8}" == "GRADING_" ]
	then
	    continue
	fi

	# -------------------------------------------------------------
        # check to see if this gradeable is already being graded
	# wait until the lock is available (up to 5 seconds)
	flock -w 5 $TODO_LOCK_FILE || log_exit "$NEXT_TO_GRADE" "flock() failed"
	if [ ! -e "$NEXT_DIRECTORY/$NEXT_ITEM" ]
	then
    	    echo "another grade_students.sh process already finished grading $NEXT_ITEM"
	    flock -u $TODO_LOCK_FILE
	    continue
	elif [ -e "$NEXT_DIRECTORY/GRADING_$NEXT_ITEM" ]
	then
    	    echo "skip $NEXT_ITEM, being graded by another grade_students.sh process"
	    flock -u $TODO_LOCK_FILE
	    continue
	else
	    # mark this file as being graded
	    touch $NEXT_DIRECTORY/GRADING_$NEXT_ITEM
	    flock -u $TODO_LOCK_FILE
	fi


	# -------------------------------------------------------------
	# GRADE THIS ITEM!

        echo "========================================================================"
        ${SUBMITTY_INSTALL_DIR}/bin/grade_item.py ${NEXT_DIRECTORY} ${NEXT_TO_GRADE} ${ARGUMENT_UNTRUSTED_USER}
        echo "========================================================================"

	# -------------------------------------------------------------
	# remove submission & the active grading tag from the todo list
	flock -w 5 $TODO_LOCK_FILE || log_exit "$NEXT_ITEM" "flock() failed"
	rm -f $NEXT_DIRECTORY/$NEXT_ITEM          || log_error "$NEXT_ITEM" "Could not delete item from todo list"
	rm -f $NEXT_DIRECTORY/GRADING_$NEXT_ITEM  || log_error "$NEXT_ITEM" "Could not delete item (w/ 'GRADING_' tag) from todo list"
	flock -u $TODO_LOCK_FILE

	# break out of the loop (need to check for new interactive items)
	graded_something=true
	break

    done

    # -------------------------------------------------------------
    # if no work was done in this iteration...
    if [ "$graded_something" = "false" ] ; then

	if "$ARGUMENT_CONTINUOUS";
	then
	    echo "grade_students.sh continuous mode"
	else
	    ((sleep_count++))
	    echo "sleep iter $sleep_count: no work"
	fi

	# either quit the loop or sleep
	if [[ $(($sleep_count * ${GRADE_STUDENTS_IDLE_SECONDS})) -gt $((${GRADE_STUDENTS_IDLE_TOTAL_MINUTES} * 60)) ]] ; then
	    break;
	else
	    # sleep for the specified number of seconds
	    sleep ${GRADE_STUDENTS_IDLE_SECONDS}
	    # make sure to reset the all_grading_done flag so we check again
	    all_grading_done=false
	    continue;
	fi
    fi

done


echo "========================================================================"
echo "ALL DONE"


