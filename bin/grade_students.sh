#!/bin/bash


# ======================================================================
# this script takes no parameters 

#     ./grade_students

if [ "$#" -ne 0 ]; then
    echo "ERROR: Illegal number of parameters" >&2
    echo "   ./grade_students  " >&2
    exit 1
fi

# ======================================================================
# these variables will be replaced by INSTALL.sh

HSS_INSTALL_DIR=__INSTALL__FILLIN__HSS_INSTALL_DIR__
HSS_DATA_DIR=__INSTALL__FILLIN__HSS_DATA_DIR__

SVN_PATH=__INSTALL__FILLIN__SVN_PATH__

AUTOGRADING_LOG_PATH=__INSTALL__FILLIN__AUTOGRADING_LOG_PATH__

# from the data directory, we expect:

# a subdirectory for each course
# HSS_DATA_DIR/courses/which_semester/course_apple/
# HSS_DATA_DIR/courses/which_semester/course_banana/

# a directory within each course for the submissions, further
# subdirectories for each assignment, then subdirectories for each
# user, and finally subdirectories for multiple submissions (version)
# HSS_DATA_DIR/courses/which_semester/course_apple/submissions/
# HSS_DATA_DIR/courses/which_semester/course_apple/submissions/hw1/
# HSS_DATA_DIR/courses/which_semester/course_apple/submissions/hw1/smithj
# HSS_DATA_DIR/courses/which_semester/course_apple/submissions/hw1/smithj/1
# HSS_DATA_DIR/courses/which_semester/course_apple/submissions/hw1/smithj/2

# input & output files are stored in a similar structure
# HSS_DATA_DIR/courses/which_semester/course_apple/test_input/hw1/first.txt
# HSS_DATA_DIR/courses/which_semester/course_apple/test_input/hw1/second.txt
# HSS_DATA_DIR/courses/which_semester/course_apple/test_output/hw1/solution1.txt
# HSS_DATA_DIR/courses/which_semester/course_apple/test_output/hw1/solution2.txt

# each assignment has executables to be run during grading
# HSS_DATA_DIR/courses/which_semester/course_apple/bin/hw1/run.out
# HSS_DATA_DIR/courses/which_semester/course_apple/bin/hw1/validate.out


# =====================================================================
# The todo lists of the most recent (ungraded) submissions have a dummy
# file in one of these directories:

# HSS_DATA_DIR/to_be_graded/which_semester__course_apple__hw1__smithj__1
# HSS_DATA_DIR/to_be_graded/which_semester__course_banana__hw2__doej__5

TO_BE_GRADED_INTERACTIVE=$HSS_DATA_DIR/to_be_graded_interactive
TO_BE_GRADED_BATCH=$HSS_DATA_DIR/to_be_graded_batch


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
# LOG_MESSAGE HELPER FUNCTION
# =====================================================================
# =====================================================================

function log_message {

    # function arguments
    timestamp=$1
    is_batch=$2
    jobname=$3
    timelabel=$4
    elapsed_time=$5
    message=$6

    # log file name
    DATE=`date +%Y%m%d`
    AUTOGRADING_LOG_FILE=$AUTOGRADING_LOG_PATH/$DATE.txt

    # first lock the file to avoid conflict with other grading processes!
    flock -w 5 $LOG_LOCK_FILE || { echo "ERROR: flock() failed. $NEXT_ITEM" >&2; exit 1; }
    printf "%-28s | %-6s | %5s | %-50s | %-6s %5s sec | %-60s | \n" \
	"$timestamp" \
	"$BASHPID" \
	"$is_batch" \
	"$jobname" \
	"$timelabel" \
	"$elapsed_time" \
	"$message" \
	>> $AUTOGRADING_LOG_FILE
    flock -u $LOG_LOCK_FILE

}



# =====================================================================
# =====================================================================
# GRADE_THIS_ITEM HELPER FUNCTION
# =====================================================================
# =====================================================================

function grade_this_item {
    
    NEXT_TO_GRADE=$1
    
    echo "========================================================================"
    echo "GRADE $NEXT_TO_GRADE"

    # --------------------------------------------------------------------
    # extract the course, assignment, user, and version from the filename
    # replace the '__' with spaces to allow for looping over list
    with_spaces=${NEXT_TO_GRADE//__/ }
    t=0
    semester="NOSEMESTER"
    course="NOCOURSE"
    assignment="NOASSIGNMENT"
    user="NOUSER"
    version="NOVERSION"
    for thing in $with_spaces; do
	((t++))
	#FIXME replace with switch statement
	if [ $t -eq 1 ]
	then
	    semester=$thing
	elif [ $t -eq 2 ]
	then
	    course=$thing
	elif [ $t -eq 3 ]
	then
	    assignment=$thing
	elif [ $t -eq 4 ]
	then
	    user=$thing
	elif [ $t -eq 5 ]
	then
	    version=$thing
	else
            #FIXME document error handling approach: leave GRADING_ file in $TO_BE_GRADED directory, assume email sent, move to next
	    echo "ERROR BAD FORMAT: $NEXT_TO_GRADE" >&2
	    return
	fi
    done
    # error checking
    # FIXME: error checking could be more significant
    if [ $semester == "NOSEMESTER" ]
    then
	echo "ERROR IN SEMESTER: $NEXT_TO_GRADE" >&2
	return
    fi
    if [ $course == "NOCOURSE" ]
    then
	echo "ERROR IN COURSE: $NEXT_TO_GRADE" >&2
	return
    fi
    if [ $assignment == "NOASSIGNMENT" ]
    then
	echo "ERROR IN ASSIGNMENT: $NEXT_TO_GRADE" >&2
	return
    fi
    if [ $user == "NOUSER" ]
    then
	echo "ERROR IN USER: $NEXT_TO_GRADE" >&2
	return
    fi
    if [ $version == "NOVERSION" ]
    then
	echo "ERROR IN VERSION: $NEXT_TO_GRADE" >&2
	return
    fi
    
    
    # --------------------------------------------------------------------
    # check to see if directory exists & is readable
    submission_path=$HSS_DATA_DIR/courses/$semester/$course/submissions/$assignment/$user/$version
    
    if [ ! -d "$HSS_DATA_DIR" ]
    then
	echo "ERROR: directory does not exist '$HSS_DATA_DIR'" >&2
	return
	fi
    if [ ! -d "$HSS_DATA_DIR/courses" ]
    then
	echo "ERROR: directory does not exist '$HSS_DATA_DIR'" >&2
	return
    fi
    if [ ! -d "$HSS_DATA_DIR/courses/$semester" ]
    then
	echo "ERROR: directory does not exist '$HSS_DATA_DIR'" >&2
	return
    fi
    # note we do not expect these directories to be readable
    
    if [ ! -d "$HSS_DATA_DIR/courses/$semester/$course" ]
    then
	echo "ERROR: directory does not exist '$HSS_DATA_DIR/courses/$semester/$course'" >&2
	return
    fi
    if [ ! -r "$HSS_DATA_DIR/courses/$semester/$course" ]
    then
	echo "ERROR: A directory is not readable '$HSS_DATA_DIR/courses/$semester/$course'" >&2
	return
    fi
    
    if [ ! -d "$HSS_DATA_DIR/courses/$semester/$course/submissions" ]
    then
	echo "ERROR: B directory does not exist '$HSS_DATA_DIR/courses/$semester/$course/submissions'" >&2
	return
    fi
    if [ ! -r "$HSS_DATA_DIR/courses/$semester/$course/submissions" ]
    then
	echo "ERROR: C directory is not readable '$HSS_DATA_DIR/courses/$semester/$course/submissions'" >&2
	return
    fi
    
    if [ ! -d "$HSS_DATA_DIR/courses/$semester/$course/submissions/$assignment" ]
    then
	echo "ERROR: D directory does not exist '$HSS_DATA_DIR/courses/$semester/$course/submissions/$assignment'" >&2
	return
    fi
    if [ ! -r "$HSS_DATA_DIR/courses/$semester/$course/submissions/$assignment" ]
    then
	echo "ERROR: E directory is not readable '$HSS_DATA_DIR/courses/$semester/$course/submissions/$assignment'" >&2
	return
    fi
    
    if [ ! -d "$HSS_DATA_DIR/courses/$semester/$course/submissions/$assignment/$user" ]
    then
	echo "ERROR: F directory does not exist '$HSS_DATA_DIR/courses/$semester/$course/submissions/$assignment/$user'" >&2
	return
    fi
    if [ ! -r "$HSS_DATA_DIR/courses/$semester/$course/submissions/$assignment/$user" ]
    then
	echo "ERROR: G directory is not readable '$HSS_DATA_DIR/courses/$semester/$course/submissions/$assignment/$user'" >&2
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
	#FIXME remove GRADING_ file
	return
    fi
    
    
    
    test_code_path="$HSS_DATA_DIR/courses/$semester/$course/test_code/$assignment"
    test_input_path="$HSS_DATA_DIR/courses/$semester/$course/test_input/$assignment"
    test_output_path="$HSS_DATA_DIR/courses/$semester/$course/test_output/$assignment"
    checkout_path="$HSS_DATA_DIR/courses/$semester/$course/checkout/$assignment/$user/$version"
    results_path="$HSS_DATA_DIR/courses/$semester/$course/results/$assignment/$user/$version"
    bin_path="$HSS_DATA_DIR/courses/$semester/$course/bin"
    
    # --------------------------------------------------------------------
    # MAKE TEMPORARY DIRECTORY & COPY THE NECESSARY FILES THERE
    tmp=`mktemp -d /tmp/temp.XXXXXXXX`
    
    submission_time=""
    if [ -e "$submission_path/.submit.timestamp" ]
    then
	submission_time=`cat $submission_path/.submit.timestamp`
    else
	echo "ERROR:  $submission_path/.submit.timestamp   does not exist!" >&2
    fi
    
    # switch to tmp directory
    pushd $tmp > /dev/null
    
    # --------------------------------------------------------------------
    # COMPILE THE SUBMITTED CODE
    
    # copy submitted files to a tmp compilation directory
    tmp_compilation=$tmp/TMP_COMPILATION
    mkdir -p $tmp_compilation
    
    
    # copy the .submit.timestamp file and any files from submission zip 
    # switched to rsync to copy dot files (just in case they're needed)
    rsync 1>/dev/null  2>&1  -r $submission_path/ $tmp_compilation ||  echo "ERROR: Failed to copy submitted files to temporary compilation directory: rsync -r $submission_path/ $tmp_compilation" >&2
    
    # use the jq json parsing command line utility to grab the svn_checkout flag from the class.json config file
    class_json_config="$HSS_DATA_DIR/courses/$semester/$course/config/class.json"
    svn_checkout=`cat $class_json_config | jq '.assignments[] | if .assignment_id == "'${assignment}'" then .svn_checkout else empty end'`
    
    # if this homework is submitted by svn, use the date/time from
    # the .submit.timestamp file and checkout the version matching
    # that date/time from the svn server
    if [ "$svn_checkout" == true ]
    then
	
	# grab the svn subdirectory (if any) from the class.json config file
	svn_subdirectory=`cat $class_json_config | jq '.assignments[] | if .assignment_id == "'${assignment}'" then .svn_subdirectory else empty end'`
	if [ $svn_subdirectory == "null" ]
	then
	    svn_subdirectory=""
	else
	    # remove double quotes from the value
	    svn_subdirectory=${svn_subdirectory//\"/}
	fi
	
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
	##############

        # first, clean out all of the old files if this is a re-run
        rm -rf "$checkout_path"
	
	# svn checkout into the archival directory 
	mkdir -p $checkout_path
	pushd $checkout_path > /dev/null
	svn co $SVN_PATH/$user/$svn_subdirectory@{"$submission_time"} . > $tmp/.submit_svn_checkout.txt 2>&1
	popd > /dev/null
	
	# copy checkout into tmp compilation directory
	rsync 1>/dev/null  2>&1  -r $checkout_path/ $tmp_compilation ||  echo "ERROR: Failed to copy checkout files into compilation directory: rsync -r $checkout_path/ $tmp_compilation" >&2
	
	svn_checkout_error_code="$?"
	if [[ "$svn_checkout_error_code" -ne 0 ]] ;
	then
	    echo "SVN CHECKOUT FAILURE $svn_checkout_error_code"
#FIXME add log_message here
#	    log_message "" 
	else
	    echo "SVN CHECKOUT OK" 
	fi
    fi
    
    # copy any instructor provided code files to tmp compilation directory
    if [ -d "$test_code_path" ]
    then
	rsync -a $test_code_path/ "$tmp_compilation" ||  echo "ERROR: Failed to copy instructor files to temporary compilation directory:  cp -rf $test_code_path/ $tmp_compilation" >&2
	#cp -rf $HSS_DATA_DIR/courses/$semester/$course/config/disallowed_words.txt "$tmp_compilation" ||  echo "ERROR: Failed to copy disallowed_words.txt to temporary directory $test_code_path : cp -rf $HSS_DATA_DIR/courses/$semester/$course/config/disallowed_words.txt $tmp_compilation" >&2
    fi
    
    pushd $tmp_compilation > /dev/null
    
    # first delete any submitted .out or .exe executable files
    rm -f *.out *.exe test*.txt
    
    if [ ! -r "$bin_path/$assignment/compile.out" ]
    then
	echo "ERROR:  $bin_path/$assignment/compile.out  does not exist/is not readable" >&2
    else
	
   	# copy compile.out to the current directory
	cp -f "$bin_path/$assignment/compile.out" $tmp_compilation/my_compile.out
	
  	# give the untrusted user read/write/execute permissions on the tmp directory & files
	chmod -R go+rwx $tmp
	
	# run the compile.out as the untrusted user
	$HSS_INSTALL_DIR/bin/untrusted_execute $tmp_compilation/my_compile.out >& $tmp/.submit_compile_output.txt
	
	compile_error_code="$?"
	if [[ "$compile_error_code" -ne 0 ]] ;
	then
	    echo "COMPILE FAILURE CODE $compile_error_code"
#FIXME add log_message here
#	    log_message "" 
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

    rsync   1>/dev/null  2>&1   -rvuzm   --include="*/"  --include="*.out"   --include="*.class"  --include="*.py"  --include="*README*"  --include="test*.txt"  --include="data/*" --exclude="*"  $tmp_compilation/  $tmp  
    
    # NOTE: Also grabbing all student data files (files with 'data/' directory in path)
    # remove the compilation directory
    rm -rf $tmp_compilation
    
    # --------------------------------------------------------------------
    # RUN RUNNER
    
    # copy input files to tmp directory
    if [ -d "$test_input_path" ]
    then
	cp -rf $test_input_path/* "$tmp" ||  echo "ERROR: Failed to copy input files to temporary directory $test_input_path to $tmp : cp -rf $test_input_path/* $tmp"  >&2
    fi
    
    # copy run.out to the tmp directory
    if [ ! -r "$bin_path/$assignment/run.out" ]
    then
	echo "ERROR:  $bin_path/$assignment/run.out  does not exist/is not readable" >&2
    else
	
	cp -f "$bin_path/$assignment/run.out" $tmp/my_run.out
	
  	# give the untrusted user read/write/execute permissions on the tmp directory & files
	chmod -R go+rwx $tmp
	
	# run the run.out as the untrusted user
	$HSS_INSTALL_DIR/bin/untrusted_execute $tmp/my_run.out >& .submit_runner_output.txt
	runner_error_code="$?"

	# change permissions of all files created by untrusted in this directory (so hwcron can archive/grade them)
	$HSS_INSTALL_DIR/bin/untrusted_execute /usr/bin/find $tmp -user untrusted -exec /bin/chmod o+r {} \;   >>  .submit_runner_output.txt 2>&1

	if [[ "$runner_error_code" -ne 0 ]] ;
	then
	    echo "RUNNER FAILURE CODE $runner_error_code"
#FIXME add log_message here
#	    log_message "" 
	else
	    echo "RUNNER OK"
	fi
    fi
    
    # --------------------------------------------------------------------
    # RUN VALIDATOR
    
    # copy output files to tmp directory  (SHOULD CHANGE THIS)
    if [ -d "$test_output_path" ]
    then
	cp -rf $test_output_path/* "$tmp" ||  echo "ERROR: Failed to copy output files to temporary directory $test_output_path to $tmp :  cp -rf $test_output_path/* $tmp" >&2
    fi
    
    if [ ! -r "$bin_path/$assignment/validate.out" ]
    then
	echo "ERROR:  $bin_path/$assignment/validate.out  does not exist/is not readable" >&2
	# continue
    else
        # echo "GOING TO RUN valgrind $bin_path/$assignment/validate.out $version $submission_time $runner_error_code"
	
        if [[ 0 -eq 0 ]] ; then
            echo "$bin_path/$assignment/validate.out" "$assignment" "$user" "$version" "$submission_time"  >& .submit_validator_output.txt
            #valgrind "$bin_path/$assignment/validate.out" "$assignment" "$user" "$version" "$submission_time"  >& .submit_validator_output.txt
            "$bin_path/$assignment/validate.out" "$assignment" "$user" "$version" "$submission_time"  >& .submit_validator_output.txt
        else
            echo '$HSS_INSTALL_DIR/bin/untrusted_execute /usr/bin/valgrind "$bin_path/$assignment/validate.out" "$assignment" "$user" "$version" "$submission_time"  >& .submit_validator_output.txt'
            "$HSS_INSTALL_DIR/bin/untrusted_execute" "/usr/bin/valgrind" "$bin_path/$assignment/validate.out" "$assignment" "$user" "$version" "$submission_time"  >& .submit_validator_output.txt
        fi
        # Non-valgrind commands
        # echo ""$bin_path/$assignment/validate.out" "$assignment" "$user" "$version" "$submission_time"  >& .submit_validator_output.txt"
        # "$bin_path/$assignment/validate.out" "$assignment" "$user" "$version" "$submission_time"  >& .submit_validator_output.txt


	validator_error_code="$?"
	if [[ "$validator_error_code" -ne 0 ]] ;
	then
	    echo "VALIDATOR FAILURE CODE $validator_error_code  course=$course  hw=$assignment  user=$user  version=$version" 1>&2
#FIXME add log_message here
#	    log_message "" 	    
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
    mkdir -p "$results_path" ||  echo "ERROR: Could not create results path $results_path" >&2
    cp  1>/dev/null  2>&1  $tmp/test*.txt $tmp/.submit* $tmp/submission.json $tmp/test*.json "$results_path"


    # FIXME: a global variable
    global_grade_result=`grep "total:" $results_path/.submit.grade`

    
    # --------------------------------------------------------------------
    # REMOVE TEMP DIRECTORY
    
    # step out of this directory
    popd > /dev/null
    # and remove the directory
    rm -rf $tmp
 
}


# =====================================================================
# (end of grade_this_item helper function)
# =====================================================================




too_many_processes_count=0
sleep_count=0



# =====================================================================
# =====================================================================
# OUTER LOOP (will eventually process all submissions)
# =====================================================================
# =====================================================================

while true; do

    # -------------------------------------------------------------
    # check for runaway processes by untrusted (this should never be
    # more that a few, the user limit is 50)
    numprocesses=1 #$(ps -u untrusted | wc -l)
    if [[ $numprocesses -gt 25 ]] ; then
	echo "ERROR: untrusted is running too many processes: " $numprocesses >&2
	((too_many_processes_count++))
	if [[ $too_many_processes_count -gt 10 ]];
	then
	    exit
	fi
	sleep 10
	continue
    fi
    too_many_processes_count=0


    # -------------------------------------------------------------
    # check for parallel grade_students scripts
    #ps -f -u hwcron | grep grade_students.sh
    #pgrep -u hwcron grade_students
    pgrep_results=$(pgrep grade_students)
    pgrep_results=( $pgrep_results ) # recast as array
    numparallel=${#pgrep_results[@]} # count elements in array
    echo "hwcron is running $numparallel parallel scripts"
    if [[ "$numparallel" -gt 5 ]] ; then
	 echo "hwcron is running too many parallel scripts: " $numparallel
	exit
    fi


    # -------------------------------------------------------------
    # -------------------------------------------------------------
    # FIND NEXT ASSIGNMENT TO GRADE (in reverse chronological order)
    # -------------------------------------------------------------
    # -------------------------------------------------------------

    graded_something=false

    # Use ls in the two directories to create a list of everything in
    # the to_do list.  Prioritize grading "interative" items before all
    # "batch" items.

    interactive_list=$(ls -1rt ${TO_BE_GRADED_INTERACTIVE} | awk '{print "'${TO_BE_GRADED_INTERACTIVE}'/"$1}')
    batch_list=$(ls -1rt ${TO_BE_GRADED_BATCH} | awk '{print "'${TO_BE_GRADED_BATCH}'/"$1}')

    for NEXT_TO_GRADE in ${interactive_list} ${batch_list}; do

	FILE_TIMESTAMP=`stat -c %Y $NEXT_TO_GRADE`
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
        # check to see if this assignment is already being graded
	# wait until the lock is available (up to 5 seconds)

	flock -w 5 $TODO_LOCK_FILE || { echo "ERROR: flock() failed. $NEXT_TO_GRADE" >&2; exit 1; }
	if [ -e "$NEXT_DIRECTORY/GRADING_$NEXT_ITEM" ]
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

	# calculate how long this job was waiting in the queue
	WAITTIME=$(($STARTTIME - $FILE_TIMESTAMP))
	
	# log the start
	log_message "`date --date="@$STARTTIME"`"   "$IS_BATCH_JOB"    "$NEXT_ITEM"  "wait:"  "$WAITTIME"  ""

	# FIXME: using a global variable to pass back the grade
	global_grade_result="ERROR: NO GRADE"
	# call the helper function
	grade_this_item $NEXT_ITEM

	# mark the end time
	ENDTIME=$(date +%s)

	# calculate how long this job was running
	ELAPSED=$(($ENDTIME - $STARTTIME))

	echo "finished with $NEXT_ITEM in ~$ELAPSED seconds"

	# -------------------------------------------------------------
	# remove submission & the active grading tag from the todo list
	flock -w 5 $TODO_LOCK_FILE || { echo "ERROR: flock() failed. $NEXT_ITEM" >&2; exit 1; }
	rm -f $NEXT_DIRECTORY/$NEXT_ITEM
	rm -f $NEXT_DIRECTORY/GRADING_$NEXT_ITEM
	flock -u $TODO_LOCK_FILE

	# log the end
	log_message "`date --date="@$ENDTIME"`"   "$IS_BATCH_JOB"    "$NEXT_ITEM"  "grade:"  "$ELAPSED" "$global_grade_result"

	# break out of the loop (need to check for new interactive items)
	graded_something=true
	break
    
    done    

    # -------------------------------------------------------------
    # if no work was done in this iteration...
    if [ "$graded_something" = "false" ] ; then
	((sleep_count++))
	echo "sleep iter $sleep_count: no work"
	if [[ $sleep_count -gt 200 ]] ; then
	    # 5 seconds (sleep) * 12 = 1 minute
	    # 5 seconds (sleep) * 120 = 10 minutes
	    # 5 seconds (sleep) * 200 = 16 minutes
	    # *** plus more time if you actually did any work! ***
	    # if you've been running for at least 16 minutes, quit (will be restarted by a cron once per 15 minutes)
	    break;
	else
	    # sleep for 5 seconds
	    sleep 5
	    # make sure to reset the all_grading_done flag so we check again
	    all_grading_done=false
	    continue;
	fi
    fi
    
done


echo "========================================================================"
echo "ALL DONE"


