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




# from the data directory, we expect:

# a subdirectory for each course
# SUBMITTY_DATA_DIR/courses/which_semester/course_apple/
# SUBMITTY_DATA_DIR/courses/which_semester/course_banana/

# a directory within each course for the submissions, further
# subdirectories for each gradeable, then subdirectories for each
# user, and finally subdirectories for multiple submissions (version)
# SUBMITTY_DATA_DIR/courses/which_semester/course_apple/submissions/
# SUBMITTY_DATA_DIR/courses/which_semester/course_apple/submissions/hw1/
# SUBMITTY_DATA_DIR/courses/which_semester/course_apple/submissions/hw1/smithj
# SUBMITTY_DATA_DIR/courses/which_semester/course_apple/submissions/hw1/smithj/1
# SUBMITTY_DATA_DIR/courses/which_semester/course_apple/submissions/hw1/smithj/2

# input & output files are stored in a similar structure
# SUBMITTY_DATA_DIR/courses/which_semester/course_apple/test_input/hw1/first.txt
# SUBMITTY_DATA_DIR/courses/which_semester/course_apple/test_input/hw1/second.txt
# SUBMITTY_DATA_DIR/courses/which_semester/course_apple/test_output/hw1/solution1.txt
# SUBMITTY_DATA_DIR/courses/which_semester/course_apple/test_output/hw1/solution2.txt

# each gradeable has executables to be run during grading
# SUBMITTY_DATA_DIR/courses/which_semester/course_apple/bin/hw1/run.out
# SUBMITTY_DATA_DIR/courses/which_semester/course_apple/bin/hw1/validate.out


# =====================================================================
# The todo lists of the most recent (ungraded) submissions have a dummy
# file in one of these directories:

# SUBMITTY_DATA_DIR/to_be_graded/which_semester__course_apple__hw1__smithj__1
# SUBMITTY_DATA_DIR/to_be_graded/which_semester__course_banana__hw2__doej__5

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

    printf "%-28s | %-6s | %5s | %-50s | %-6s %5s %3s | %s\n" \
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



# =====================================================================
# =====================================================================
# GRADE_THIS_ITEM HELPER FUNCTION
# =====================================================================
# =====================================================================

function grade_this_item {

    NEXT_DIRECTORY=$1
    NEXT_TO_GRADE=$2

    echo "GRADE $NEXT_TO_GRADE"

    # --------------------------------------------------------------------
    # The queue file name contains the necessary information to
    # identify the gradeable to grade (separated by '__'), but let's
    # instead parse it out of the json file contents.

    semester=`cat ${NEXT_DIRECTORY}/${NEXT_TO_GRADE} | jq .semester | tr -d '"'`
    course=`cat ${NEXT_DIRECTORY}/${NEXT_TO_GRADE} | jq .course | tr -d '"'`
    gradeable=`cat ${NEXT_DIRECTORY}/${NEXT_TO_GRADE} | jq .gradeable| tr -d '"'`
    user=`cat ${NEXT_DIRECTORY}/${NEXT_TO_GRADE} | jq .user | tr -d '"'`
    team=`cat ${NEXT_DIRECTORY}/${NEXT_TO_GRADE} | jq .team | tr -d '"'`
    who=`cat ${NEXT_DIRECTORY}/${NEXT_TO_GRADE} | jq .who | tr -d '"'`
    is_team=`cat ${NEXT_DIRECTORY}/${NEXT_TO_GRADE} | jq .is_team | tr -d '"'`
    version=`cat ${NEXT_DIRECTORY}/${NEXT_TO_GRADE} | jq .version | tr -d '"'`

    # error checking (make sure nothing is null)
    if [ -z "$semester" ]
    then
	echo "ERROR IN SEMESTER: $NEXT_TO_GRADE" >&2
	return
    fi
    if [ -z "$course" ]
    then
	echo "ERROR IN COURSE: $NEXT_TO_GRADE" >&2
	return
    fi
    if [ -z "$gradeable" ]
    then
	echo "ERROR IN GRADEABLE: $NEXT_TO_GRADE" >&2
	return
    fi
#    if [ -z "$user" ]
#    then
#	echo "ERROR IN USER: $NEXT_TO_GRADE" >&2
#	return
#    fi
#    if [ -z "$team" ]
#    then
#	echo "ERROR IN TEAM: $NEXT_TO_GRADE" >&2
#	return
#    fi
    if [ -z "$who" ]
    then
	echo "ERROR IN WHO: $NEXT_TO_GRADE" >&2
	return
    fi
    if [ -z "$is_team" ]
    then
        echo "ERROR IN IS_TEAM: $NEXT_TO_GRADE" >&2
    return
    fi
    if [ -z "$version" ]
    then
	echo "ERROR IN VERSION: $NEXT_TO_GRADE" >&2
	return
    fi


    # --------------------------------------------------------------------
    # check to see if directory exists & is readable
    submission_path=$SUBMITTY_DATA_DIR/courses/$semester/$course/submissions/$gradeable/$who/$version

    if [ ! -d "$SUBMITTY_DATA_DIR" ]
    then
	echo "ERROR: directory does not exist '$SUBMITTY_DATA_DIR'" >&2
	return
	fi
    if [ ! -d "$SUBMITTY_DATA_DIR/courses" ]
    then
	echo "ERROR: directory does not exist '$SUBMITTY_DATA_DIR'" >&2
	return
    fi
    if [ ! -d "$SUBMITTY_DATA_DIR/courses/$semester" ]
    then
	echo "ERROR: directory does not exist '$SUBMITTY_DATA_DIR'" >&2
	return
    fi
    # note we do not expect these directories to be readable

    if [ ! -d "$SUBMITTY_DATA_DIR/courses/$semester/$course" ]
    then
	echo "ERROR: directory does not exist '$SUBMITTY_DATA_DIR/courses/$semester/$course'" >&2
	return
    fi
    if [ ! -r "$SUBMITTY_DATA_DIR/courses/$semester/$course" ]
    then
	echo "ERROR: A directory is not readable '$SUBMITTY_DATA_DIR/courses/$semester/$course'" >&2
	return
    fi

    if [ ! -d "$SUBMITTY_DATA_DIR/courses/$semester/$course/submissions" ]
    then
	echo "ERROR: B directory does not exist '$SUBMITTY_DATA_DIR/courses/$semester/$course/submissions'" >&2
	return
    fi
    if [ ! -r "$SUBMITTY_DATA_DIR/courses/$semester/$course/submissions" ]
    then
	echo "ERROR: C directory is not readable '$SUBMITTY_DATA_DIR/courses/$semester/$course/submissions'" >&2
	return
    fi

    if [ ! -d "$SUBMITTY_DATA_DIR/courses/$semester/$course/submissions/$gradeable" ]
    then
	echo "ERROR: D directory does not exist '$SUBMITTY_DATA_DIR/courses/$semester/$course/submissions/$gradeable'" >&2
	return
    fi
    if [ ! -r "$SUBMITTY_DATA_DIR/courses/$semester/$course/submissions/$gradeable" ]
    then
	echo "ERROR: E directory is not readable '$SUBMITTY_DATA_DIR/courses/$semester/$course/submissions/$gradeable'" >&2
	return
    fi

    if [ ! -d "$SUBMITTY_DATA_DIR/courses/$semester/$course/submissions/$gradeable/$who" ]
    then
	echo "ERROR: F directory does not exist '$SUBMITTY_DATA_DIR/courses/$semester/$course/submissions/$gradeable/$who'" >&2
	return
    fi
    if [ ! -r "$SUBMITTY_DATA_DIR/courses/$semester/$course/submissions/$gradeable/$who" ]
    then
	echo "ERROR: G directory is not readable '$SUBMITTY_DATA_DIR/courses/$semester/$course/submissions/$gradeable/$who'" >&2
	return
    fi

    if [ ! -d "$submission_path" ]
    then
	echo "ERROR: directory does not exist '$submission_path'" >&2
	# this submission does not exist, remove it from the queue
	return
    fi
    if [ ! -r "$submission_path" ]
    then
	echo "ERROR: H directory is not readable '$submission_path'  FIXME!  STUDENT WASN'T GRADED!!" >&2
	# leave this submission file for next time (hopefully
	# permissions will be corrected then)
        #FIXME remove GRADING_ file ???
	log_error "$NEXT_TO_GRADE" "Directory is unreadable, student wasn't graded"
	return
    fi



    test_code_path="$SUBMITTY_DATA_DIR/courses/$semester/$course/test_code/$gradeable"
    test_input_path="$SUBMITTY_DATA_DIR/courses/$semester/$course/test_input/$gradeable"
    test_output_path="$SUBMITTY_DATA_DIR/courses/$semester/$course/test_output/$gradeable"
    checkout_path="$SUBMITTY_DATA_DIR/courses/$semester/$course/checkout/$gradeable/$who/$version"
    results_path_tmp="$SUBMITTY_DATA_DIR/courses/$semester/$course/results/$gradeable/$who/$version"
    bin_path="$SUBMITTY_DATA_DIR/courses/$semester/$course/bin"

    results_path="$results_path_tmp/OLD"

    # grab a copy of the current history.json file (if it exists)
    #global_history_file_location_tmp=${results_path_tmp}/history.json
    #global_history_file_location=${results_path}/history.json
    #if [ -e "$global_history_file_location" ]
    #then
    #    tmp_history_filename=`mktemp`
    #    cp -f $global_history_file_location  $tmp_history_filename
    #fi


    # --------------------------------------------------------------------
    # MAKE TEMPORARY DIRECTORY & COPY THE NECESSARY FILES THERE
    tmp=`mktemp -d /tmp/temp.XXXXXXXX`

    submission_time=""
    if [ -e "$submission_path/.submit.timestamp" ]
    then
	submission_time=`cat $submission_path/.submit.timestamp`
    else
	log_error "$NEXT_TO_GRADE" "$submission_path/.submit.timestamp   does not exist!"
    fi
    # chop of first & last characters
    global_submission_time=`date -d "${submission_time}"`

    # switch to tmp directory
    pushd $tmp > /dev/null

    # --------------------------------------------------------------------
    # COMPILE THE SUBMITTED CODE

    # copy submitted files to a tmp compilation directory
    tmp_compilation=$tmp/TMP_COMPILATION
    mkdir -p $tmp_compilation


    # copy the .submit.timestamp file and any files from submission zip
    rsync 1>/dev/null  2>&1  -r $submission_path/ $tmp_compilation || log_error "$NEXT_TO_GRADE" "Failed to copy submitted files to temporary compilation directory: rsync -r $submission_path/ $tmp_compilation [ exitcode=$? ]"

    # use the jq json parsing command line utility to grab the svn_checkout flag from the config file
    json_config="$SUBMITTY_DATA_DIR/courses/$semester/$course/config/form/form_${gradeable}.json"
    step=`cat ${json_config} | jq .upload_type`
    # if [ "$step" == "\"Repository\"" ]; then svn_checkout=true; else svn_checkout=false; fi

    # also save the due date
    global_gradeable_deadline=`cat ${json_config} | jq .date_due`
    # need to chop off first & last characters (double quotes)
    global_gradeable_deadline=`date -d "${global_gradeable_deadline:1:-1}"`

    # if this homework is submitted by svn, use the date/time from
    # the .submit.timestamp file and checkout the version matching
    # that date/time from the svn server
    # if [ "$svn_checkout" == true ]
    # then

        # grab the svn subdirectory (if any) from the config file
    #     svn_subdirectory=`cat ${json_config} | jq .subdirectory`
    #     if [ $svn_subdirectory == "null" ]
    #     then
    #         svn_subdirectory=""
    #     else
            # remove double quotes from the value
    #         svn_subdirectory=${svn_subdirectory//\"/}
    #     fi

        ##############
        # SVN documentation
        #
        # students can access SVN only their own top SVN repo directory with this command:
        # svn co https://csci2600svn.cs.rpi.edu/USERNAME --username USERNAME
        #
        # the hwcron user can access all students SVN repo directories with this command:
        # svn co svn+ssh://csci2600svn.cs.rpi.edu/local/svn/csci2600/USERNAME
        #
        # -r specifies which version to checkout (by timestamp)
        # BUT... we want to use the @ syntax.  often -r and @ are the
        # same, but if a student has renamed a directory and then
        # recreated it, -r and @ are different.  FIXME: Look up the
        # documentation and improve this comment.
        #
        ##############

        # first, clean out all of the old files if this is a re-run
    #     rm -rf "$checkout_path"

        # svn checkout into the archival directory
    #     mkdir -p $checkout_path
    #     pushd $checkout_path > /dev/null
    #     svn co $SVN_PATH/$who/$svn_subdirectory@{"$submission_time"} . > $tmp/results_log_svn_checkout.txt 2>&1
    #     popd > /dev/null

        # copy checkout into tmp compilation directory
    #     rsync 1>/dev/null  2>&1  -r $checkout_path/ $tmp_compilation || log_error "$NEXT_TO_GRADE" "Failed to copy checkout files into compilation directory: rsync -r $checkout_path/ $tmp_compilation"

    #     svn_checkout_error_code="$?"
    #     if [[ "$svn_checkout_error_code" -ne 0 ]] ;
    #     then
    #         log_error "$NEXT_TO_GRADE" "SVN CHECKOUT FAILURE $svn_checkout_error_code"
    #     else
    #         echo "SVN CHECKOUT OK"
    #     fi
    # fi

    # copy any instructor provided code files to tmp compilation directory
    if [ -d "$test_code_path" ]
    then
        rsync -a $test_code_path/ "$tmp_compilation" || log_error "$NEXT_TO_GRADE" "Failed to copy instructor files to temporary compilation directory:  cp -rf $test_code_path/ $tmp_compilation"
    fi

    pushd $tmp_compilation > /dev/null

    # first delete any submitted .out or .exe executable files
    rm -f *.out *.exe test*.txt

    if [ ! -r "$bin_path/$gradeable/compile.out" ]
    then
	log_error "$NEXT_TO_GRADE" "$bin_path/$gradeable/compile.out does not exist/is not readable"
    else

   	# copy compile.out to the current directory
	cp -f "$bin_path/$gradeable/compile.out" $tmp_compilation/my_compile.out

  	# give the untrusted user read/write/execute permissions on the tmp directory & files
	chmod -R go+rwx $tmp

	# run the compile.out as the untrusted user
	#echo '$SUBMITTY_INSTALL_DIR/bin/untrusted_execute  "${ARGUMENT_UNTRUSTED_USER}"  $tmp_compilation/my_compile.out "$gradeable" "$who" "$version" "$submission_time" >& $tmp/results_log_compile.txt'
	$SUBMITTY_INSTALL_DIR/bin/untrusted_execute  "${ARGUMENT_UNTRUSTED_USER}"  $tmp_compilation/my_compile.out "$gradeable" "$who" "$version" "$submission_time" >& $tmp/results_log_compile.txt

	compile_error_code="$?"
	if [[ "$compile_error_code" -ne 0 ]] ;
	then
	    log_error "$NEXT_TO_GRADE" "COMPILE FAILURE CODE $compile_error_code"
	else
	    echo "COMPILE OK"
	fi
    fi

    # return to the main tmp directory
    popd > /dev/null


    # move all executable files from the compilation directory to the main tmp directory
    # Note: Must preserve the directory structure of compiled files (esp for Java)

    # at the same time grab the README files and the testXX_ STDOUT, STDERR, & execute_logfiles
    # FIXME: This might need to be revised depending on future needs...

    #  -r  recursive
    #  -m  prune empty directories
    #  --include="*/"  match all subdirectories
    #  --include="*.XXX"  grab all .XXX files
    #  --exclude="*"  exclude everything else

    $SUBMITTY_INSTALL_DIR/bin/untrusted_execute  "${ARGUMENT_UNTRUSTED_USER}"  /usr/bin/find $tmp_compilation -user "${ARGUMENT_UNTRUSTED_USER}" -exec /bin/chmod o+r {} \;   >>  results_log_runner.txt 2>&1
    
    rsync   1>/dev/null  2>&1   -rvuzm   --include="*/"  --include="*.out"  --include="*.class"  --include="*.py" --include="*.s" --include="*.pl"  --include="*.rkt" --include="*.png" --include="*.pdf" --include="*.jpg"  --include="*README*"  --include="test*.txt" --include="data/*" 	--exclude="*"  $tmp_compilation/  $tmp
    # NOTE: Also grabbing all student data files (files with 'data/' directory in path)

    # remove the compilation directory
    $SUBMITTY_INSTALL_DIR/bin/untrusted_execute  "${ARGUMENT_UNTRUSTED_USER}"  /bin/rm -rf $tmp_compilation  > /dev/null  2>&1
    $SUBMITTY_INSTALL_DIR/bin/untrusted_execute  "${ARGUMENT_UNTRUSTED_USER}"  $tmp_compilation  > /dev/null  2>&1
    rm -rf $tmp_compilation

    # --------------------------------------------------------------------
    # RUN RUNNER

    # copy input files to tmp directory
    if [ -d "$test_input_path" ]
    then
	#cp -rf $test_input_path/* "$tmp" || log_error "$NEXT_TO_GRADE" "Failed to copy input files to temporary directory $test_input_path to $tmp : cp -rf $test_input_path/* $tmp"
	cp -rf $test_input_path/* "$tmp" > /dev/null 2>&1
    fi

    # copy run.out to the tmp directory
    if [ ! -r "$bin_path/$gradeable/run.out" ]
    then
	log_error "$NEXT_TO_GRADE" "$bin_path/$gradeable/run.out does not exist/is not readable"
    else

	cp -f "$bin_path/$gradeable/run.out" $tmp/my_run.out

  	# give the untrusted user read/write/execute permissions on the tmp directory & files
	chmod -R go+rwx $tmp
        # remove the read/write permissions for the compilation log
        chmod 660 results_log_compile.txt
        # remove the execute bit for any text files
	chmod -Rf go-x *.txt

	# run the run.out as the untrusted user
	#echo '$SUBMITTY_INSTALL_DIR/bin/untrusted_execute  "${ARGUMENT_UNTRUSTED_USER}" $tmp/my_run.out "$gradeable" "$who" "$version" "$submission_time" >& results_log_runner.txt'
	$SUBMITTY_INSTALL_DIR/bin/untrusted_execute  "${ARGUMENT_UNTRUSTED_USER}" $tmp/my_run.out "$gradeable" "$who" "$version" "$submission_time" >& results_log_runner.txt
	runner_error_code="$?"

	# change permissions of all files created by untrusted in this directory (so hwcron can archive/grade them)
	$SUBMITTY_INSTALL_DIR/bin/untrusted_execute  "${ARGUMENT_UNTRUSTED_USER}"  /usr/bin/find $tmp -user "${ARGUMENT_UNTRUSTED_USER}" -exec /bin/chmod o+r {} \;   >>  results_log_runner.txt 2>&1


	# FIXME
	# ugly cleanup dr memory stuff (not sure why this needs to be added now... was working last semester)
	$SUBMITTY_INSTALL_DIR/bin/untrusted_execute  "${ARGUMENT_UNTRUSTED_USER}"  /bin/rm -rf $tmp/symcache
	$SUBMITTY_INSTALL_DIR/bin/untrusted_execute  "${ARGUMENT_UNTRUSTED_USER}"  /bin/rm -rf $tmp/DrMemory-*
	# need to revisit directory & file permissions, and decide who will be responsible for deleting this

	# this didn't fix it (didn't give hwcron ability to delete these files
	# also need to add execute on the directories...
	#$SUBMITTY_INSTALL_DIR/bin/untrusted_execute  "${ARGUMENT_UNTRUSTED_USER}"  /usr/bin/find $tmp -user "${ARGUMENT_UNTRUSTED_USER}" -type d -exec /bin/chmod o+x {} \;   >>  results_log_runner.txt 2>&1



	if [[ "$runner_error_code" -ne 0 ]] ;
	then
	    log_error "$NEXT_TO_GRADE" "RUNNER FAILURE CODE $runner_error_code"
	else
	    echo "RUNNER OK"
	fi
    fi

    # --------------------------------------------------------------------
    # RUN VALIDATOR

    # copy output files to tmp directory  (SHOULD CHANGE THIS)
    if [ -d "$test_output_path" ]
    then
	cp -rf $test_output_path/* "$tmp" || log_error "$NEXT_TO_GRADE" "Failed to copy output files to temporary directory $test_output_path to $tmp :  cp -rf $test_output_path/* $tmp"
    fi

    if [ ! -r "$bin_path/$gradeable/validate.out" ]
    then
	log_error "$NEXT_TO_GRADE" "$bin_path/$gradeable/validate.out does not exist/is not readable"
    else

	#FIXME: do we still need valgrind here?
        if [[ 0 -eq 0 ]] ; then
            echo "$bin_path/$gradeable/validate.out" "$gradeable" "$who" "$version" "$submission_time"  >& results_log_validator.txt
            "$bin_path/$gradeable/validate.out" "$gradeable" "$who" "$version" "$submission_time"  >& results_log_validator.txt
        else
            echo '$SUBMITTY_INSTALL_DIR/bin/untrusted_execute  "${ARGUMENT_UNTRUSTED_USER}"  /usr/bin/valgrind "$bin_path/$gradeable/validate.out" "$gradeable" "$who" "$version" "$submission_time"  >& results_log_validator.txt'
            "$SUBMITTY_INSTALL_DIR/bin/untrusted_execute  "${ARGUMENT_UNTRUSTED_USER}" " "/usr/bin/valgrind" "$bin_path/$gradeable/validate.out" "$gradeable" "$who" "$version" "$submission_time"  >& results_log_validator.txt
        fi

	validator_error_code="$?"
	if [[ "$validator_error_code" -ne 0 ]] ;
	then
	    log_error "$NEXT_TO_GRADE" "VALIDATOR FAILURE CODE $validator_error_code  course=$course  hw=$gradeable  who=$who  version=$version"
	else
	    echo "VALIDATOR OK"
	fi
    fi

    # --------------------------------------------------------------------
    # MAKE RESULTS DIRECTORY & COPY ALL THE FILES THERE

    # Get working directory back to bin
    cd "$bin_path"

    # clean out all of the old files if this is a re-run
    rm -rf "$results_path"

    # Make directory structure in results if it doesn't exist
    mkdir -p "$results_path" || log_error "$NEXT_TO_GRADE" "Could not create results path $results_path"

    cp  1>/dev/null  2>&1  $tmp/test*.txt $tmp/test*.png $tmp/test*.html $tmp/results_log_*txt $tmp/results.json $tmp/grade.txt $tmp/test*.json "$results_path"


    # FIXME: a global variable

    if [ -e $results_path/grade.txt ] ;
    then
	global_grade_result=`grep "Automatic grading total:" $results_path/grade.txt`
    else
	global_grade_result="ERROR: $results_path/grade.txt does not exist"
    fi


    if [[ $global_grade_result == "" ]] ;
    then
	global_grade_result="WARNING: $results_path/grade.txt does not have a total score"
    fi


    # move the copied results history (if it exists) back into results folder
    #if [ -e "$tmp_history_filename" ]
    #then
    #    mv $tmp_history_filename $global_history_file_location
    #    # and fix permissions
    #    ta_group=`stat -c "%G"  ${results_path}`
    #    chgrp ${ta_group} $global_history_file_location
    #    chmod g+r $global_history_file_location
    #fi

    # --------------------------------------------------------------------
    # REMOVE TEMP DIRECTORY

    # step out of this directory

    popd > /dev/null
    # and remove the directory
    find . -exec ls -lta {} \; > $results_path/results_log_done.txt 2>&1
    $SUBMITTY_INSTALL_DIR/bin/untrusted_execute  "${ARGUMENT_UNTRUSTED_USER}"  /bin/rm -rf $tmp  > /dev/null  2>&1
    rm -rf $tmp
}


# =====================================================================
# (end of grade_this_item helper function)
# =====================================================================




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


	# mark the start time
	STARTTIME=$(date +%s)

	# when was this job put in the queue?
	FILE_TIMESTAMP=`stat -c %Y $NEXT_TO_GRADE 2>&1`

	# calculate how long this job was waiting in the queue
	integer_reg_expression='^[0-9]+$'
	if ! [[ $FILE_TIMESTAMP =~ $integer_reg_expression ]] ; then
	    # FIXME NOTE: if the file does not exist (shouldn't
	    # happen, but we are seeing it, needs debugging)
	    log_error "$NEXT_ITEM" "$FILE_TIMESTAMP"
	    FILE_TIMESTAMP=STARTTIME+1
	fi
	WAITTIME=$(($STARTTIME - ${FILE_TIMESTAMP:-0}))


	# log the start
	log_message "$IS_BATCH_JOB"  "$NEXT_ITEM"  "wait:"  "$WAITTIME"  ""


	# FIXME: using a global variable to pass back the grade
	global_grade_result="ERROR: NO GRADE"
	global_submission_time=""
	global_gradeable_deadline=""
	# call the helper function
        echo "========================================================================"
	grade_this_item $NEXT_DIRECTORY $NEXT_ITEM

        ${SUBMITTY_INSTALL_DIR}/bin/grade_item.py ${NEXT_DIRECTORY} ${NEXT_TO_GRADE} ${ARGUMENT_UNTRUSTED_USER}
        echo "========================================================================"

	# mark the end time
	ENDTIME=$(date +%s)

	# calculate how long this job was running
	ELAPSED=$(($ENDTIME - $STARTTIME))

	# -------------------------------------------------------------
#        # create/append to the results history
#        sec_deadline=`date -d "${global_gradeable_deadline}" +%s`
#        sec_submission=`date -d "${global_submission_time}" +%s`
#        seconds_late=$((sec_submission-sec_deadline))
#        ${SUBMITTY_INSTALL_DIR}/bin/write_grade_history.py  \
#                               "$global_history_file_location" \
#                               "$global_gradeable_deadline" \
#                               "$global_submission_time" \
#                               "$seconds_late" \
#                               "`date -d @$FILE_TIMESTAMP`" \
#                               "$IS_BATCH_JOB" \
#                               "`date -d @$STARTTIME`" \
#                               "$WAITTIME" \
#                               "`date -d @$ENDTIME`" \
#                               "$ELAPSED" \
#                               "$global_grade_result"
#
#        cp "$global_history_file_location" "$global_history_file_location_tmp"  
        
        #---------------------------------------------------------------------
        # WRITE OUT VERSION DETAILS

        ${SUBMITTY_INSTALL_DIR}/bin/insert_database_version_data.py \
                               "${semester}" \
                               "${course}" \
                               "${gradeable}" \
                               "${user}" \
                               "${team}" \
                               "${who}" \
                               "${is_team}" \
                               "${version}"
        
	echo "finished with $NEXT_ITEM in ~$ELAPSED seconds"

	# -------------------------------------------------------------
	# remove submission & the active grading tag from the todo list
	flock -w 5 $TODO_LOCK_FILE || log_exit "$NEXT_ITEM" "flock() failed"
	rm -f $NEXT_DIRECTORY/$NEXT_ITEM          || log_error "$NEXT_ITEM" "Could not delete item from todo list"
	rm -f $NEXT_DIRECTORY/GRADING_$NEXT_ITEM  || log_error "$NEXT_ITEM" "Could not delete item (w/ 'GRADING_' tag) from todo list"
	flock -u $TODO_LOCK_FILE

	# log the end
	log_message "$IS_BATCH_JOB"  "$NEXT_ITEM"  "grade:"  "$ELAPSED" "$global_grade_result"

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


