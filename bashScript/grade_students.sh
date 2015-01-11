#!/bin/bash

# ======================================================================
# this script takes in a single parameter, the base path of all of
# the submission server files

#     ./grade_students <base_path>

if [ "$#" -ne 2 ]; then
    echo "ERROR: Illegal number of parameters" >&2
    echo "   ./grade_students  absolute_path_to_base_directory  relative_path_of_to_be_graded_directory" >&2
    exit 1
fi

base_path="$1"
TO_BE_GRADED="$2"

# from that directory, we expect:

# a subdirectory for each course
# BASE_PATH/courses/which_semester/course_apple/
# BASE_PATH/courses/which_semester/course_banana/

# a directory within each course for the submissions, further
# subdirectories for each assignment, then subdirectories for each
# user, and finally subdirectories for multiple submissions (version)
# BASE_PATH/courses/which_semester/course_apple/submissions/
# BASE_PATH/courses/which_semester/course_apple/submissions/hw1/
# BASE_PATH/courses/which_semester/course_apple/submissions/hw1/smithj
# BASE_PATH/courses/which_semester/course_apple/submissions/hw1/smithj/1
# BASE_PATH/courses/which_semester/course_apple/submissions/hw1/smithj/2

# input & output files are stored in a similar structure
# BASE_PATH/courses/which_semester/course_apple/test_input/hw1/first.txt
# BASE_PATH/courses/which_semester/course_apple/test_input/hw1/second.txt
# BASE_PATH/courses/which_semester/course_apple/test_output/hw1/solution1.txt
# BASE_PATH/courses/which_semester/course_apple/test_output/hw1/solution2.txt

# each assignment has executables to be run during grading
# BASE_PATH/courses/which_semester/course_apple/bin/hw1/run.out
# BASE_PATH/courses/which_semester/course_apple/bin/hw1/validate.out


# =====================================================================
# The todo list of the most recent (ungraded) submissions have a dummy
# file in this directory:

# BASE_PATH/to_be_graded/which_semester__course_apple__hw1__smithj__1
# BASE_PATH/to_be_graded/which_semester__course_banana__hw2__doej__5



# NOTE ON OUTPUT: cron job stdout/stderr gets emailed
# debugging type output can be sent to stdout, which we'll redirect to /dev/null in the cron job
# all problematic errors should get set to stderr  (>&2)  so that an email will be sent
echo "Grade all submissions in $base_path/$TO_BE_GRADED/"


all_grading_done=false

too_many_processes_count=0

sleep_count=0


# SETUP THE DIRECTORY LOCK FILE
# arbitrarily using file descriptor 200
exec 200>/var/lock/homework_submissions_server_lockfile || exit 1


# OUTER LOOP
# will eventually process all submissions
while true; do

    # if no work was done on the last loop...
    if [ "$all_grading_done" = "true" ] ; then
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


    # check for runaway processes by untrusted (this should never be more that a few, the user limit is 50)
    numprocesses=$(ps -u untrusted | wc -l)
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


    # check for parallel grade_students scripts
    #ps -f -u hwcron | grep grade_students.sh
    #pgrep -u hwcron grade_students
    pgrep_results=$(pgrep -u hwcron grade_students)
    pgrep_results=( $pgrep_results ) # recast as array
    numparallel=${#pgrep_results[@]} # count elements in array
    echo "hwcron is running $numparallel parallel scripts"
    if [[ "$numparallel" -gt 5 ]] ; then
	 echo "hwcron is running too many parallel scripts: " $numparallel
	exit
    fi



    # =====================================================================
    # FIND NEXT ASSIGNMENT TO GRADE (in reverse chronological order)
    # =====================================================================


    # reset this variable
    all_grading_done=true



    for NEXT_TO_GRADE in `cd $base_path/$TO_BE_GRADED && ls -tr`; do


	# skip the active grading tags
	if [ "${NEXT_TO_GRADE:0:8}" == "GRADING_" ]
	then
	    continue
	fi


        # check to see if this assignment is already being graded
	# wait until the lock is available (up to 5 seconds)
	flock -w 5 200 || { echo "ERROR: flock() failed. $NEXT_TO_GRADE" >&2; exit 1; }
	if [ -e "$base_path/$TO_BE_GRADED/GRADING_$NEXT_TO_GRADE" ]
	then
    	     echo "skip $NEXT_TO_GRADE, being graded by another grade_students.sh process"
	    flock -u 200
	    continue
	else
	    # mark this file as being graded
	    touch $base_path/$TO_BE_GRADED/GRADING_$NEXT_TO_GRADE
	    flock -u 200
	fi


	 echo "========================================================================"
	 echo "GRADE $NEXT_TO_GRADE"

	STARTTIME=$(date +%s)


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
		continue
	    fi
	done
        # error checking
        # FIXME: error checking could be more significant
	if [ $semester == "NOSEMESTER" ]
	then
	    echo "ERROR IN SEMESTER: $NEXT_TO_GRADE" >&2
	    continue
	fi
	if [ $course == "NOCOURSE" ]
	then
	    echo "ERROR IN COURSE: $NEXT_TO_GRADE" >&2
	    continue
	fi
	if [ $assignment == "NOASSIGNMENT" ]
	then
	    echo "ERROR IN ASSIGNMENT: $NEXT_TO_GRADE" >&2
	    continue
	fi
	if [ $user == "NOUSER" ]
	then
	    echo "ERROR IN USER: $NEXT_TO_GRADE" >&2
	    continue
	fi
	if [ $version == "NOVERSION" ]
	then
	    echo "ERROR IN VERSION: $NEXT_TO_GRADE" >&2
	    continue
	fi


	# --------------------------------------------------------------------
        # check to see if directory exists & is readable
# <<<<<<< HEAD
# 	submission_path=$base_path/$course/submissions/$assignment/$user/$version
# =======
	submission_path=$base_path/courses/$semester/$course/submissions/$assignment/$user/$version
# >>>>>>> server
	#echo "check directory '$submission_path'"

	if [ ! -d "$base_path" ]
	then
	    echo "ERROR: directory does not exist '$base_path'" >&2
	    continue
	fi
	if [ ! -d "$base_path/courses" ]
	then
	    echo "ERROR: directory does not exist '$base_path'" >&2
	    continue
	fi
	if [ ! -d "$base_path/courses/$semester" ]
	then
	    echo "ERROR: directory does not exist '$base_path'" >&2
	    continue
	fi
        # note we do not expect these directories to be readable

	if [ ! -d "$base_path/courses/$semester/$course" ]
	then
	    echo "ERROR: directory does not exist '$base_path/courses/$semester/$course'" >&2
	    continue
	fi
	if [ ! -r "$base_path/courses/$semester/$course" ]
	then
	    echo "ERROR: A directory is not readable '$base_path/courses/$semester/$course'" >&2
	    continue
	fi

	if [ ! -d "$base_path/courses/$semester/$course/submissions" ]
	then
	    echo "ERROR: B directory does not exist '$base_path/courses/$semester/$course/submissions'" >&2
	    continue
	fi
	if [ ! -r "$base_path/courses/$semester/$course/submissions" ]
	then
	    echo "ERROR: C directory is not readable '$base_path/courses/$semester/$course/submissions'" >&2
	    continue
	fi

	if [ ! -d "$base_path/courses/$semester/$course/submissions/$assignment" ]
	then
	    echo "ERROR: D directory does not exist '$base_path/courses/$semester/$course/submissions/$assignment'" >&2
	    continue
	fi
	if [ ! -r "$base_path/courses/$semester/$course/submissions/$assignment" ]
	then
	    echo "ERROR: E directory is not readable '$base_path/courses/$semester/$course/submissions/$assignment'" >&2
	    continue
	fi

	if [ ! -d "$base_path/courses/$semester/$course/submissions/$assignment/$user" ]
	then
	    echo "ERROR: F directory does not exist '$base_path/courses/$semester/$course/submissions/$assignment/$user'" >&2
	    continue
	fi
	if [ ! -r "$base_path/courses/$semester/$course/submissions/$assignment/$user" ]
	then
	    echo "ERROR: G directory is not readable '$base_path/courses/$semester/$course/submissions/$assignment/$user'" >&2
	    continue
	fi

	if [ ! -d "$submission_path" ]
	then
	    echo "ERROR: directory does not exist '$submission_path'" >&2
	    # this submission does not exist, remove it from the queue
	    rm -f $base_path/$TO_BE_GRADED/$NEXT_TO_GRADE
	    continue
	fi
	if [ ! -r "$submission_path" ]
	then
	    echo "ERROR: H directory is not readable '$submission_path'" >&2
	    # leave this submission file for next time (hopefully
	    # permissions will be corrected then)
	    #FIXME remove GRADING_ file
	    continue
	fi



#
# <<<<<<< HEAD
# 	test_code_path="$base_path/$course/test_code/$assignment"
# 	test_input_path="$base_path/$course/test_input/$assignment"
# 	test_output_path="$base_path/$course/test_output/$assignment"
# 	results_path="$base_path/$course/results/$assignment/$user/$version"
# 	bin_path="$base_path/$course/bin"
#
# =======
	test_code_path="$base_path/courses/$semester/$course/test_code/$assignment"
	test_input_path="$base_path/courses/$semester/$course/test_input/$assignment"
	test_output_path="$base_path/courses/$semester/$course/test_output/$assignment"
	results_path="$base_path/courses/$semester/$course/results/$assignment/$user/$version"
	bin_path="$base_path/courses/$semester/$course/bin"

# >>>>>>> server

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
	mkdir $tmp_compilation
	cp 1>/dev/null  2>&1  -r $submission_path/* $tmp_compilation ||  echo "ERROR: Failed to copy to temporary directory $submission_path" >&2


        # copy any instructor code files to tmp directory
	if [ -d "$test_code_path" ]
	then
	    cp -rf $test_code_path/ "$tmp_compilation" ||  echo "ERROR: Failed to copy to temporary directory $test_code_path" >&2
	    cp -rf $base_path/courses/$semester/$course/config/disallowed_words.txt "$tmp_compilation" ||  echo "ERROR: Failed to copy disallowed_words.txt to temporary directory $test_code_path" >&2
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

#	    $base_path/bin/untrusted_runscript $tmp/my_compile.out >& .submit_compile_output.txt

        # echo "$tmp_compilation/my_compile.out"
	    # $tmp_compilation/my_compile.out >& $tmp/.submit_compile_output.txt

	    $base_path/bin/untrusted_runscript $tmp_compilation/my_compile.out >& $tmp/.submit_compile_output.txt

	    compile_error_code="$?"
	    if [[ "$compile_error_code" -ne 0 ]] ;
	    then
		echo "COMPILE FAILURE CODE $compile_error_code"
	    else
		echo "COMPILE OK"
	    fi
	fi

	# return to the main tmp directory
	popd > /dev/null


	# move all executable files from the to the main tmp directory
	# FIXME: not really what we want for the "FILE_EXISTS" command....
        cp  1>/dev/null  2>&1  $tmp_compilation/README*.txt  $tmp_compilation/*.out $tmp_compilation/test*.txt $tmp



	# remove the directory
	rm -rf $tmp_compilation

	# --------------------------------------------------------------------
        # RUN RUNNER

        # copy input files to tmp directory
	if [ -d "$test_input_path" ]
	then
	    cp -rf $test_input_path/* "$tmp" ||  echo "ERROR: Failed to copy to temporary directory $test_input_path" >&2
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
        echo "$base_path/bin/untrusted_runscript $tmp/my_run.out >& .submit_runner_output.txt"
	    $base_path/bin/untrusted_runscript $tmp/my_run.out >& .submit_runner_output.txt

	    runner_error_code="$?"
	    if [[ "$runner_error_code" -ne 0 ]] ;
	    then
		echo "RUNNER FAILURE CODE $runner_error_code"
	    else
		echo "RUNNER OK"
	    fi
	fi

	# --------------------------------------------------------------------
        # RUN VALIDATOR

        # copy output files to tmp directory  (SHOULD CHANGE THIS)
	if [ -d "$test_output_path" ]
	then
	    cp -rf $test_output_path/* "$tmp" ||  echo "ERROR: Failed to copy to temporary directory $test_output_path" >&2
	fi

	if [ ! -r "$bin_path/$assignment/validate.out" ]
	then
	    echo "ERROR:  $bin_path/$assignment/validate.out  does not exist/is not readable" >&2
	    # continue
	else
        # echo "GOING TO RUN valgrind $bin_path/$assignment/validate.out $version $submission_time $runner_error_code"

        if [[ 0 -eq 0 ]] ; then
            echo "$bin_path/$assignment/validate.out" "$assignment" "$user" "$version" "$submission_time"  >& .submit_validator_output.txt
            valgrind "$bin_path/$assignment/validate.out" "$assignment" "$user" "$version" "$submission_time"  >& .submit_validator_output.txt
        else
            echo '$base_path/bin/untrusted_runscript /usr/bin/valgrind "$bin_path/$assignment/validate.out" "$assignment" "$user" "$version" "$submission_time"  >& .submit_validator_output.txt'
            "$base_path/bin/untrusted_runscript" "/usr/bin/valgrind" "$bin_path/$assignment/validate.out" "$assignment" "$user" "$version" "$submission_time"  >& .submit_validator_output.txt
        fi
        # Non-valgrind commands
        # echo ""$bin_path/$assignment/validate.out" "$assignment" "$user" "$version" "$submission_time"  >& .submit_validator_output.txt"
        # "$bin_path/$assignment/validate.out" "$assignment" "$user" "$version" "$submission_time"  >& .submit_validator_output.txt


	    validator_error_code="$?"
	    if [[ "$validator_error_code" -ne 0 ]] ;
	    then
	     echo "VALIDATOR FAILURE CODE $validator_error_code  course=$course  hw=$assignment  user=$user  version=$version" 1>&2

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


	# --------------------------------------------------------------------
        # REMOVE TEMP DIRECTORY

	# step out of this directory
	popd > /dev/null
	# and remove the directory
        rm -rf $tmp


	# remove submission & the active grading tag from the todo list
	flock -w 5 200 || { echo "ERROR: flock() failed. $NEXT_TO_GRADE" >&2; exit 1; }
	rm -f $base_path/$TO_BE_GRADED/$NEXT_TO_GRADE
	rm -f $base_path/$TO_BE_GRADED/GRADING_$NEXT_TO_GRADE
	flock -u 200


	ENDTIME=$(date +%s)
	 echo "finished with $NEXT_TO_GRADE in ~$(($ENDTIME - $STARTTIME)) seconds"

	all_grading_done=false
	break
    done
done

echo "========================================================================"
echo "ALL DONE"
