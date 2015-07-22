#!/bin/bash


# base_path
base_path="$1"

# find all submissions in these subdirectories
pattern="$2"

#optional argument...
if [ "$#" -gt 1 ]; then
    TO_BE_GRADED="$3"
else
    TO_BE_GRADED="to_be_graded2"
fi


pattern_length=${#pattern}


#################################################################################
#################################################################################
function check_match {

    # single argument, to be compared to the input pattern 
    candidate=$1
    candidate_length=${#candidate}

    #echo "CHECK MATCH '$pattern' '$candidate'"
    #echo "lengths '$pattern_length' '$candidate_length'"

    if [ "$candidate_length" -lt "$pattern_length" ]
    then
	#echo "candidate not long enough yet" 
	# not long enough yet
	short_pattern=${pattern:0:$candidate_length}

#	echo "truncated check '$short_pattern' '$candidate'"
    
	if [ "$short_pattern" != "$candidate" ]
	then
	    # does not match!
#	    echo "does not match"  "$short_pattern" != "$candidate" 
	    return 2  
	else
	    # matches so far...
	    return 1 
	fi
    fi

    short_candidate=${candidate:0:$pattern_length}

#    echo "TEST '$pattern' '$short_candidate'"

    if [ "$pattern" != "$short_candidate" ]
    then
	#echo "returning 2"
	#echo "NO MATCH                          $submission_path"
        # equal or longer, does not match
	return 2  
    fi



    # equal or longer, matches!
    #echo "returning 0"
    return 0  
}
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
			touch $base_path/$TO_BE_GRADED/$next_to_grade
		    fi
		done
	    done
	done
    done
done
