#!/bin/bash

# ======================================================================
# these variables will be replaced by INSTALL.sh

HSS_INSTALL_DIR=__INSTALL__FILLIN__HSS_INSTALL_DIR__
HSS_DATA_DIR=__INSTALL__FILLIN__HSS_DATA_DIR__

# ======================================================================

while true; do
    total_count=`ls -1 2>/dev/null $HSS_DATA_DIR/to_be_graded/* | wc -l`
    active_count=`ls -1 2>/dev/null $HSS_DATA_DIR/to_be_graded/GRADING* | wc -l`
    echo "todo count: " $[$total_count-$active_count] "  active grading: " $active_count
    if [ $total_count -eq "0" ]; then
	exit
    fi    
    if (( "$total_count" < "10" )); then
	sleep 1
    else
	sleep 10
    fi    

done

# ======================================================================
