#!/bin/bash

# ======================================================================
# these variables will be replaced by INSTALL.sh

HSS_INSTALL_DIR=__INSTALL__FILLIN__HSS_INSTALL_DIR__
HSS_DATA_DIR=__INSTALL__FILLIN__HSS_DATA_DIR__

# ======================================================================

while true; do
    interactive_count=`ls -1 2>/dev/null $HSS_DATA_DIR/to_be_graded_interactive/* | wc -l`
    interactive_grading_count=`ls -1 2>/dev/null $HSS_DATA_DIR/to_be_graded_interactive/GRADING* | wc -l`

    batch_count=`ls -1 2>/dev/null $HSS_DATA_DIR/to_be_graded_batch/* | wc -l`
    batch_grading_count=`ls -1 2>/dev/null $HSS_DATA_DIR/to_be_graded_batch/GRADING* | wc -l`

    printf 'INTERACTIVE todo:%3d '    $[$interactive_count-$interactive_grading_count] 
    if [ $interactive_grading_count -eq "0" ]; then
	printf '                 '
    else
	printf '(grading:%3d)    '    $interactive_grading_count 
    fi
    printf 'BATCH todo:%3d '          $[$batch_count-$batch_grading_count] 


    if [ ! $batch_grading_count -eq "0" ]; then
	printf '(grading:%3d)'        $batch_grading_count 
    fi
    printf '\n'


    total_count=$[$interactive_count+$batch_count]

    if [ $total_count -eq "0" ]; then
	exit
    fi    
    if (( "$total_count" < "10" )); then
	sleep 1
    else
	sleep 1
    fi    

done

# ======================================================================
