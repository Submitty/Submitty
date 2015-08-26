#!/bin/bash

# This script is used to monitor the contents of the two grading
# queues (interactive & batch).

# USAGE:
# ./grading_done.sh  
#     [or]
# ./grading_done.sh continuous

# ======================================================================
# these variables will be replaced by INSTALL.sh

HSS_INSTALL_DIR=__INSTALL__FILLIN__HSS_INSTALL_DIR__
HSS_DATA_DIR=__INSTALL__FILLIN__HSS_DATA_DIR__

# ======================================================================
# some error checking on the queues (& permissions of this user)

INTERACTIVE_QUEUE=$HSS_DATA_DIR/to_be_graded_interactive
BATCH_QUEUE=$HSS_DATA_DIR/to_be_graded_batch

if [ ! -d "$INTERACTIVE_QUEUE" ]; then
    echo "ERROR: interactive queue $INTERACTIVE_QUEUE does not exist"
    exit
fi

if [ ! -d "$BATCH_QUEUE" ]; then 
    echo "ERROR: batch queue $BATCH_QUEUE does not exist"
    exit
fi

if [ ! -r "$INTERACTIVE_QUEUE" ]; then
    # most instructors do not have read access to the interactive queue
    echo "WARNING: interactive queue $INTERACTIVE_QUEUE is not readable"
fi

if [ ! -r "$BATCH_QUEUE" ]; then 
    echo "ERROR: batch queue $BATCH_QUEUE is not readable"
    exit
fi

# ======================================================================
# by default, this script quits when the queues are both empty
# optional argument will allow this to run continuously

continuous=false
if [ "$#" -eq 1 ] && [ "$1" = "continuous" ]; then 
    continuous=true
fi


# ======================================================================


while true; do


    pgrep_results=$(pgrep grade_students)
    pgrep_results=( $pgrep_results ) # recast as array
    numparallel=${#pgrep_results[@]} # count elements in array


    # collect the counts from the two queues
    interactive_count=`ls -1 2>/dev/null $INTERACTIVE_QUEUE/* | wc -l`
    interactive_grading_count=`ls -1 2>/dev/null $INTERACTIVE_QUEUE/GRADING* | wc -l`
    batch_count=`ls -1 2>/dev/null $BATCH_QUEUE/* | wc -l`
    batch_grading_count=`ls -1 2>/dev/null $BATCH_QUEUE/GRADING* | wc -l`


    # print out the counts

    printf 'GRADING PROCESSES:%3d       ' $numparallel  

    # most instructors do not have read access to the interactive queue
    if [ -r "$INTERACTIVE_QUEUE" ]; then
	printf 'INTERACTIVE todo:%3d '    $[$interactive_count-$interactive_grading_count] 
	if [ $interactive_grading_count -eq "0" ]; then
	    printf '                 '
	else
	    printf '(grading:%3d)    '    $interactive_grading_count 
	fi
    fi
    printf 'BATCH todo:%3d '          $[$batch_count-$batch_grading_count] 
    if [ ! $batch_grading_count -eq "0" ]; then
	printf '(grading:%3d)'        $batch_grading_count 
    fi
    printf '\n'


    # quit when the queues are empty
    total_count=$[$interactive_count+$batch_count]
    if [ $total_count -eq "0" ] && ! $continuous ; then
	exit
    fi    


    # pause before checking again
    sleep 5

done

# ======================================================================
