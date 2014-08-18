#!/bin/bash
 
# ======================================================================
# this script takes in a single parameter, the base path of the all of
# the submission server files

#     ./grade_students <base_path>

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
# The newest (ungraded) submissions have a dummy file in this
# directory:

# BASE_PATH/to_be_graded/course_apple__hw1__smithj__1
# BASE_PATH/to_be_graded/course_banana__hw2__doej__5

echo "Grade all submissions in $base_path/to_be_graded/"


# OUTER LOOP 
# will eventually process all submissions 

all_graded=false
repeat=0

while [ "$all_graded" != "true" ]; do
    all_graded=true


    # check for runaway processes (this should never be more that a few, the user limit is 50)
    numprocesses=$(ps -u untrusted | wc -l)
    if [[ $numprocesses -gt 20 ]] ; then
	echo "untrusted is running too many processes" $numprocesses
	all_graded=false
	((repeat++))
	if [[ $repeat -gt 10 ]]; 
	then 
	    exit
	fi
	sleep 1
	continue
    fi
    repeat=0



    # =====================================================================
    # FIND NEXT ASSIGNMENT TO GRADE (in reverse chronological order)
    # =====================================================================

    for NEXT_TO_GRADE in `cd $base_path/to_be_graded && ls -tr`; do


	echo "========================================================================"
	echo "GRADE $NEXT_TO_GRADE"
	
	STARTTIME=$(date +%s)


	# FIXME start of idea to make robust... use flock (needs more work)


	# --------------------------------------------------------------------
        # extract the course, assignment, user, and version from the filename
	with_spaces=${NEXT_TO_GRADE//__/ }
	t=0
	course="NOCOURSE"
	assignment="NOASSIGNMENT"
	user="NOUSER"
	version="NOVERSION"
	for thing in $with_spaces; do	
	    ((t++))
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
	    rm -rf $base_path/to_be_graded/$NEXT_TO_GRADE
	    continue
	fi
	if [ ! -r "$submission_path" ]
	then
	    echo "ERROR: directory is not readable '$submission_path'"
	    # leave this submission file for next time (hopefully
	    # permissions will be corrected then)
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
	cp 1>/dev/null  2>&1  -r $submission_path/* $tmp || echo "ERROR: Failed to copy to temporary directory"









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
	cp "$bin_path/$assignment/run.out" $tmp/my_run.out

	# give the untrusted user read/write/execute permissions on the tmp directory & files
	chmod o+rwx $tmp
	chmod g+rwx $tmp
	chmod o+rwx $tmp/*
	chmod g+rwx $tmp/*

	# run the run.out as the untrusted user
	$base_path/bin/untrusted_runscript $tmp/my_run.out &> .submit_runner_output.txt

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

        "$bin_path/$assignment/validate.out" "$version" "$submission_time" "$runner_error_code" &> .submit_validator_output.txt
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
        rm -rf "$results_path/$path"

        # Make directory structure in results if it doesn't exist
        mkdir -p "$results_path/$path" || echo "ERROR: Could not create results path $results_path/$path"

        cp  1>/dev/null  2>&1  $tmp/* $tmp/.* "$results_path/$path"
        # cp  1>/dev/null  2>&1  $tmp/test*_cout.txt $tmp/test*_cerr.txt $tmp/test*_out.txt $tmp/submission.json "$results_path/$path"


	# --------------------------------------------------------------------
        # REMOVE TEMP DIRECTORY
        rm -rf $tmp


	# remove submission from the todo list
	# start of idea to make robust... use flock (needs more work)
	#flock ($base_path/to_be_graded/my_lock,LOCK_EX)
	rm -rf $base_path/to_be_graded/$NEXT_TO_GRADE
	#flock ($base_path/to_be_graded/my_lock,LOCK_UN)

	
	ENDTIME=$(date +%s)
	echo "finished with $NEXT_TO_GRADE in ~$(($ENDTIME - $STARTTIME)) seconds"

	all_graded=false
	break
    done
done

echo "========================================================================"
echo "ALL DONE"
