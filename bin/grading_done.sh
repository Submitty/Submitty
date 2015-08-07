#!/bin/bash

HSS_INSTALL_DIR=/usr/local/hss
HSS_DATA_DIR=/var/local/hss

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
