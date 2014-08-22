#!/bin/bash
 
# ======================================================================
# this script takes in a single parameter, the base path of all of
# the submission server files

#     ./grade_students <base_path>

#FIXME: check to make sure exactly one argument
base_path="$1"

# from that directory, we expect:

# a subdirectory for each course
# BASE_PATH/course_apple/
# BASE_PATH/course_banana/

# a directory within each course for the submissions, further
# subdirectories for each assignment, then subdirectories for each
# user, and finally subdirectories for multiple submissions (version)
# BASE_PATH/course_apple/submissions/
# BASE_PATH/course_apple/submissions/hw1/
# BASE_PATH/course_apple/submissions/hw1/smithj
# BASE_PATH/course_apple/submissions/hw1/smithj/1
# BASE_PATH/course_apple/submissions/hw1/smithj/2

# input & output files are stored in a similar structure
# BASE_PATH/course_apple/test_input/hw1/first.txt
# BASE_PATH/course_apple/test_input/hw1/second.txt
# BASE_PATH/course_apple/test_output/hw1/solution1.txt
# BASE_PATH/course_apple/test_output/hw1/solution2.txt

# each assignment has executables to be run during grading
# BASE_PATH/course_apple/bin/hw1/run.out
# BASE_PATH/course_apple/bin/hw1/validate.out


# =====================================================================
# The todo list of the most recent (ungraded) submissions have a dummy
# file in this directory:

# BASE_PATH/to_be_graded/course_apple__hw1__smithj__1
# BASE_PATH/to_be_graded/course_banana__hw2__doej__5

#FIXME cron job stdout/stderr gets emailed
echo "Grade all submissions in $base_path/to_be_graded/"


# OUTER LOOP 
# will eventually process all submissions 

all_grading_done=false

too_many_processes_count=0

sleep_count=0


# SETUP THE DIRECTORY LOCK FILE 
# arbitrarily using file descriptor 200
exec 200>/var/lock/homework_submissions_server_lockfile || exit 1


while true; do

    # if no work was done on the last loop...
    if [ "$all_grading_done" = "true" ] ; then
	((sleep_count++))
	echo "sleep iter $sleep_count: no work"
	if [[ $sleep_count -gt 100 ]] ; then
	    # if you've been running for several minutes, quit (will be restarted by a cron once per minute)
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
	echo "untrusted is running too many processes: " $numprocesses
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
#FIXME, look into pgreg (process grep)
    numparallel=$(ps -f -u hwcron | grep grade_students.sh | wc -l)
    if [[ "$numparallel" -gt 5 ]] ; then
	echo "hwcron is running too many parallel scripts: " $numparallel
	exit
    fi



    # =====================================================================
    # FIND NEXT ASSIGNMENT TO GRADE (in reverse chronological order)
    # =====================================================================


    # reset this variable
    all_grading_done=true



    for NEXT_TO_GRADE in `cd $base_path/to_be_graded && ls -tr`; do


	# skip the active grading tags
	if [ "${NEXT_TO_GRADE:0:8}" == "GRADING_" ]
	then
	    continue
	fi

	
        # check to see if this assignment is already being graded
	# wait until the lock is available (up to 5 seconds)
	flock -w 5 200 || { echo "ERROR: flock() failed." >&2; exit 1; }
	if [ -e "$base_path/to_be_graded/GRADING_$NEXT_TO_GRADE" ]
	then
    	    echo "skip $NEXT_TO_GRADE, being graded by another grade_students.sh process"
	    flock -u 200
	    continue
	else
	    # mark this file as being graded
	    touch $base_path/to_be_graded/GRADING_$NEXT_TO_GRADE
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
	course="NOCOURSE"
	assignment="NOASSIGNMENT"
	user="NOUSER"
	version="NOVERSION"
	for thing in $with_spaces; do	
	    ((t++))
	    #FIXME replace with switch statement
	    if [ $t -eq 1 ]
	    then
		course=$thing
	    elif [ $t -eq 2 ]
	    then
		assignment=$thing
	    elif [ $t -eq 3 ]
	    then
		user=$thing
	    elif [ $t -eq 4 ]
	    then
		version=$thing
	    else
#FIXME document error handling approach: leave GRADING_ file in to_be_graded directory, assume email sent, move to next
		echo "FORMAT ERROR: $NEXT_TO_GRADE"
		continue
	    fi
	done
        # error checking
        # FIXME: error checking could be more significant
	if [ $course == "NOCOURSE" ] 
	then 
	    echo "ERROR IN COURSE: $course"
	    continue 
	fi
	if [ $assignment == "NOASSIGNMENT" ] 
	then 
	    echo "ERROR IN ASSIGNMENT: $assignment"
	    continue 
	fi
	if [ $user == "NOUSER" ] 
	then 
	    echo "ERROR IN USER: $user"
	    continue
	fi
	if [ $version == "NOVERSION" ] 
	then 
	    echo "ERROR IN VERSION: $version"
	    continue 
	fi

    
	# --------------------------------------------------------------------
        # check to see if directory exists & is readable
	submission_path=$base_path/$course/submissions/$assignment/$user/$version 
	#echo "check directory '$submission_path'"

	if [ ! -d "$base_path" ]
	then
	    echo "ERROR: directory does not exist '$base_path'"
	    continue
	fi
        # note we do not expect $base_path to be readable

	if [ ! -d "$base_path/$course" ]
	then
	    echo "ERROR: directory does not exist '$base_path/$course'"
	    continue
	fi
	if [ ! -r "$base_path/$course" ]
	then
	    echo "ERROR: directory is not readable '$base_path/$course'"
	    continue
	fi

	if [ ! -d "$base_path/$course/submissions" ]
	then
	    echo "ERROR: directory does not exist '$base_path/$course/submissions'"
	    continue
	fi
	if [ ! -r "$base_path/$course/submissions" ]
	then
	    echo "ERROR: directory is not readable '$base_path/$course/submissions'"
	    continue
	fi

	if [ ! -d "$base_path/$course/submissions/$assignment" ]
	then
	    echo "ERROR: directory does not exist '$base_path/$course/submissions/$assignment'"
	    continue
	fi
	if [ ! -r "$base_path/$course/submissions/$assignment" ]
	then
	    echo "ERROR: directory is not readable '$base_path/$course/submissions/$assignment'"
	    continue
	fi

	if [ ! -d "$base_path/$course/submissions/$assignment/$user" ]
	then
	    echo "ERROR: directory does not exist '$base_path/$course/submissions/$assignment/$user'"
	    continue
	fi
	if [ ! -r "$base_path/$course/submissions/$assignment/$user" ]
	then
	    echo "ERROR: directory is not readable '$base_path/$course/submissions/$assignment/$user'"
	    continue
	fi

	if [ ! -d "$submission_path" ]
	then
	    echo "ERROR: directory does not exist '$submission_path'"
	    # this submission does not exist, remove it from the queue
	    rm -f $base_path/to_be_graded/$NEXT_TO_GRADE
	    continue
	fi
	if [ ! -r "$submission_path" ]
	then
	    echo "ERROR: directory is not readable '$submission_path'"
	    # leave this submission file for next time (hopefully
	    # permissions will be corrected then)
	    #FIXME remove GRADING_ file
	    continue
	fi




	test_input_path="$base_path/$course/test_input/$assignment"
	test_output_path="$base_path/$course/test_output/$assignment"
	results_path="$base_path/$course/results/$assignment/$user/$version"
	bin_path="$base_path/$course/bin"
	

	# --------------------------------------------------------------------
        # MAKE TEMPORARY DIRECTORY & COPY THE NECESSARY FILES THERE
	tmp=`mktemp -d`


        # copy submitted files to tmp directory
	cp 1>/dev/null  2>&1  -r $submission_path/* "$tmp" || echo "ERROR: Failed to copy to temporary directory"









        # copy input files to tmp directory
	if [ -d "$test_input_path" ]
	then
	    cp -rf $test_input_path/* "$tmp" || echo "ERROR: Failed to copy to temporary directory"       
	fi
	








        # copy output files to tmp directory  (SHOULD CHANGE THIS)
	if [ -d "$test_output_path" ]
	then
	    cp -rf $test_output_path/* "$tmp" || echo "ERROR: Failed to copy to temporary directory"       
	fi
	
	submission_time="$(date -r $submission_path "+%F %T")"



        # switch to tmp directory
# FIXME pushd?
	cd $tmp
	
	
	# --------------------------------------------------------------------
        # COMPILE THE SUBMITTED CODE
        #clang++ -Wall *.cpp -o a.out &> .submit_compilation_output.txt
	g++ -Wall *.cpp -o a.out    &> .submit_compilation_output.txt
	compile_error_code=$?
	

	if [[ "$compile_error_code" -ne 0 ]] ;
	then
	    echo "COMPILE ERROR CODE $compile_error_code"
	else
	    echo "COMPILE OK"
	fi
	
	
	# --------------------------------------------------------------------
        # RUN RUNNER
	# copy run.out to the tmp directory
	if [ ! -r "$bin_path/$assignment/run.out" ]
	then
	    echo "ERROR:  $bin_path/$assignment/run.out  does not exist/is not readable"
	    continue
	fi
	cp -f "$bin_path/$assignment/run.out" $tmp/my_run.out

	# give the untrusted user read/write/execute permissions on the tmp directory & files
	#FIXME: copying in subdirs but not making readable here
	chmod -R go+rwx $tmp

	# run the run.out as the untrusted user
	$base_path/bin/untrusted_runscript $tmp/my_run.out 1> .submit_runner_output.txt 2> .submit_runner_errors.txt

	runner_error_code="$?"
	if [[ "$runner_error_code" -ne 0 ]] ;
	then
	    echo "RUNNER ERROR CODE $runner_error_code"
	else
	    echo "RUNNER OK"
	fi
	
	
	# --------------------------------------------------------------------
        # RUN VALIDATOR
	if [ ! -r "$bin_path/$assignment/validate.out" ]
	then
	    echo "ERROR:  $bin_path/$assignment/validate.out  does not exist/is not readable"
	    continue
	fi

        echo "GOING TO RUN valgrind $bin_path/$assignment/validate.out $version $submission_time $runner_error_code"
        valgrind "$bin_path/$assignment/validate.out" "$version" "$submission_time" "$runner_error_code" >& .submit_validator_output.txt 
	validator_error_code="$?"
	if [[ "$validator_error_code" -ne 0 ]] ;
	then
	    echo "VALIDATOR ERROR CODE $validator_error_code"
	else
	    echo "VALIDATOR OK"
	fi
	

	# --------------------------------------------------------------------
        # MAKE RESULTS DIRECTORY & COPY ALL THE FILES THERE

        # Get working directory back to bin
	cd "$bin_path"

        # clean out all of the old files if this is a re-run
        rm -rf "$results_path"

        # Make directory structure in results if it doesn't exist
        mkdir -p "$results_path" || echo "ERROR: Could not create results path $results_path"

        cp  1>/dev/null  2>&1  $tmp/* $tmp/.* "$results_path"
        # cp  1>/dev/null  2>&1  $tmp/test*_cout.txt $tmp/test*_cerr.txt $tmp/test*_out.txt $tmp/submission.json "$results_path/$path"


	# --------------------------------------------------------------------
        # REMOVE TEMP DIRECTORY
        rm -rf $tmp


	# remove submission & the active grading tag from the todo list
	flock -w 5 200 || { echo "ERROR: flock() failed." >&2; exit 1; }
	rm -f $base_path/to_be_graded/$NEXT_TO_GRADE
	rm -f $base_path/to_be_graded/GRADING_$NEXT_TO_GRADE
	flock -u 200

	
	ENDTIME=$(date +%s)
	echo "finished with $NEXT_TO_GRADE in ~$(($ENDTIME - $STARTTIME)) seconds"

	all_grading_done=false
	break
    done
done

echo "========================================================================"
echo "ALL DONE"
