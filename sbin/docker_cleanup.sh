#!/bin/bash                                                                                                                                               

# An apparent bug in docker may leave stuck zombie networks after
# autograding has finished.  This docker_cleanup script will be run by
# the daemon user as root to detect and forceably remove any stuck
# docker networks.

# this script must be run by root or sudo
if [[ "$UID" -ne "0" ]] ; then
    echo "ERROR: This script must be run by root or sudo"
    exit 1
fi

mkdir -p "/var/local/submitty/logs/docker_cleanup/"
logfile_name="/var/local/submitty/logs/docker_cleanup/$(date +%Y%m%d).txt"
echo  "" >> "${logfile_name}"

# =================================================================================
function cleanup_dead_networks() {
    name=${1}

    # if no arguments were passed to this script, check for
    # autograding on each worker before cleaning up networks
    if [ $# -eq 0 ]; then
        # if the autograding_tmp directory is non-empty,
        # there is active autograding for that worker
        if test -n "$(ls /var/local/submitty/autograding_tmp/"${name}")"; then
            date_time=$(date "+%Y%m%d %H:%M:%S")
            echo "${date_time} | ${name} has grading in progress" >> "${logfile_name}"
            return
        fi
    fi

    # check for docker networks matching the worker name
    networks=$(docker network ls | grep "${name}" | awk '{print $2}')
    if test -z "${networks}"; then
        #echo "${date_time} | ${name} has no zombie docker network" >> "${logfile_name}"
        if [ $# -eq 1 ]; then
            echo "no docker networks to cleanup"
        fi
        return;
    fi

    date_time=$(date "+%Y%m%d %H:%M:%S")
    echo "${date_time} | ${name} has a zombie docker network(s), force docker restart required" >> "${logfile_name}"

    # forceably cleanup the network(s) - restart seems to be necessary
    # NOTE: This will likely break docker autograding current in progress
    # by other workers on the same machine.
    systemctl restart docker

    date_time=$(date "+%Y%m%d %H:%M:%S")
    echo "${date_time} | force docker restart complete" >> "${logfile_name}"

    for network in ${networks}; do
        date_time=$(date "+%Y%m%d %H:%M:%S")
        echo "${date_time} | removing zombie docker network ${network}" >> "${logfile_name}"
        docker network rm "${network}"
        if [ $# -eq 1 ]; then
            echo "killed zombie ${network}"
        fi
    done

    date_time=$(date "+%Y%m%d %H:%M:%S")
    echo "${date_time} | force cleanup of zombie docker networks complete" >> "${logfile_name}"
}
# =================================================================================

if [ $# -eq 0 ]; then
    # loop over all workers
    date_time=$(date "+%Y%m%d %H:%M:%S")
    echo "${date_time} | docker_cleanup.sh started" >> "${logfile_name}"
    for dir in /var/local/submitty/autograding_tmp/*; do
        name=$(basename "${dir}")
        cleanup_dead_networks "${name}"
    done
    date_time=$(date "+%Y%m%d %H:%M:%S")
    echo "${date_time} | docker_cleanup.sh finished" >> "${logfile_name}"
elif [ $# -eq 1 ]; then
    # only cleanup one worker
    date_time=$(date "+%Y%m%d %H:%M:%S")
    echo "${date_time} | docker_cleanup.sh $1 started" >> "${logfile_name}"
    cleanup_dead_networks "${1}" 
    date_time=$(date "+%Y%m%d %H:%M:%S")
    echo "${date_time} | docker_cleanup.sh $1 finished" >> "${logfile_name}"
else
    date_time=$(date "+%Y%m%d %H:%M:%S")
    echo "${date_time} | docker_cleanup.sh - bad arguments" >> "${logfile_name}"
fi  
