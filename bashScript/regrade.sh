#!/bin/bash

#################################################################################
#
# Expected directory structure:
# <BASE_PATH>/courses/<SEMESTER>/<COURSES>/submissions/<HW>/<USERNAME>/<VERSION#>
#
# This script will find all submissions that match the provided
# pattern and add them to the grading queue.
#
#################################################################################

# check the number of arguments
if [ "$#" -ne 1 ] && [ "$#" -ne 2 ]; then 
    echo "USAGE:"
    echo "  regrade.sh  <(absolute) PATTERN PATH>"
    echo "  regrade.sh  <(absolute) PATTERN PATH>  <TO_BE_GRADED_DIRECTORY (relative)>"
    exit
fi

# find all submissions in these subdirectories
pattern="$1"

#optional argument...
if [ "$#" -gt 1 ]; then
    TO_BE_GRADED="$2"
else
    TO_BE_GRADED="to_be_graded2"
fi

pattern_length=${#pattern}
base_path=${pattern%/courses*}
base_path_length=${#base_path}

# ensure we extracted the base path
if [ "$pattern_length" -eq "$base_path_length" ]; then 
    echo "ERROR:  PATTERN PATH " $pattern " should include 'courses' subdirectory "
    exit
fi

#################################################################################
#################################################################################

function check_match {

    # single argument, to be compared to the input pattern 
    candidate=$1
    candidate_length=${#candidate}

    if [ "$candidate_length" -lt "$pattern_length" ]
    then
	# not long enough yet
	short_pattern=${pattern:0:$candidate_length}

	if [ "$short_pattern" != "$candidate" ]
	then
	    # does not match!
	    return 2  
	else
	    # matches so far...
	    return 1 
	fi
    fi

    short_candidate=${candidate:0:$pattern_length}

    if [ "$pattern" != "$short_candidate" ]
    then
        # equal or longer, does not match
	return 2  
    fi

    # equal or longer, matches!
    return 0  
}

#################################################################################
#################################################################################

echo "looking in '$base_path' for submissions that match '$pattern'"

for semester in `cd $base_path/courses && ls -d [fs]* 2> /dev/null`; do

    if [ ! -d $base_path/courses/$semester/ ]
    then
	continue
    fi

    check_match $base_path/courses/$semester/
    var=$?
    if [ "$var" == "2" ]
    then
	continue
    fi

    echo matching semester: $semester


    for course in `cd $base_path/courses/$semester && ls -d csci* 2> /dev/null`; do


	if [ ! -d $base_path/courses/$semester/$course/ ]
	then
	    continue
	fi
	if [ ! -d $base_path/courses/$semester/$course/submissions/ ]
	then
	    continue
	fi
	
	check_match $base_path/courses/$semester/$course/submissions/
	var=$?
	if [ "$var" == "2" ]
	then
	    continue
	fi

	echo matching course: $course

	
	for assignment in `cd $base_path/courses/$semester/$course/submissions/ && ls -d *  2> /dev/null`; do

	    if [ ! -d $base_path/courses/$semester/$course/submissions/$assignment/ ]
	    then
		continue
	    fi
	    
	    check_match $base_path/courses/$semester/$course/submissions/$assignment/
	    var=$?
	    if [ "$var" == "2" ]
	    then
		continue
	    fi

	    echo matching assignment: $assignment	    
	    
	    for user in `cd $base_path/courses/$semester/$course/submissions/$assignment && ls -d *  2> /dev/null`; do

		if [ ! -d $base_path/courses/$semester/$course/submissions/$assignment/$user/ ]
		then
		    continue
		fi
		
		check_match $base_path/courses/$semester/$course/submissions/$assignment/$user/
		var=$?
		if [ "$var" == "2" ]
		then
		    continue
		fi
		echo matching user: $user		

		
		for version in `cd $base_path/courses/$semester/$course/submissions/$assignment/$user && ls -d *  2> /dev/null`; do
		    submission_path=$base_path/courses/$semester/$course/submissions/$assignment/$user/$version 
		    
		    if [ -d $submission_path ]
		    then
			check_match $submission_path
			var=$?
			if [ "$var" != "0" ]
			then
			    continue
			fi


			if [ "$version" = "ACTIVE" ] 
			then
			    continue
			fi
			if [ "$version" = "LAST" ] 
			then
			    continue
			fi
			
			next_to_grade=$semester"__"$course"__"$assignment"__"$user"__"$version
			echo "GRADE THIS: $next_to_grade"

			# collect the submissions (don't add them just yet...)
			my_queue=("${my_queue[@]}" "$next_to_grade")
		    fi
		done
	    done
	done
    done
done


#################################################################################
#################################################################################

# if there are alot of matching submissions, first confirm that we want to regrade all of them
queue_size=${#my_queue[@]}
if [ "$queue_size" -gt 20 ]
then
    echo "Found $queue_size matching submissions.  Add to queue? [y/n]"
    read value
    if [ "$value" != "y" ] 
    then
	echo "quitting"
	exit
    fi
fi

# add the matching submissions to the grading queue
for i in "${my_queue[@]}"
do
touch $base_path/$TO_BE_GRADED/$i
done
echo "Added $queue_size submissions to $TO_BE_GRADED queue for regrading."
