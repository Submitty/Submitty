#!/bin/bash

########################################################################
# This script was used to run experiments replaying portions of
# submission history with and without docker containers.
########################################################################

if [[ $EUID -ne 0 ]]; then
    echo "This script must be run as root"
    exit 1
fi

test_user=INSERT_USERNAME


rm -f /home/$(test_user)/PAPER_DATA/*.txt

#list all containers
#sudo su -c 'docker container ls' hwcron


kill_stuff () {

    echo 'kill stuff'
    
    # kill all containers
    sudo su -c 'docker stop $(docker ps -aq)' hwcron
    sudo su -c 'docker rm $(docker ps -aq)' hwcron
    sudo su -c 'docker container ls' hwcron

    # killall performance_monitor.py
    ps -ef | grep performance_monitor.py | grep -v grep | awk '{print $2}' | xargs kill -9

    # kill all old jobs
    rm -f /var/local/submitty/to_be_graded_queue/*

    # clean up files
    rm -rf /var/local/submitty/autograding_tmp/untrusted*/tmp
    
}

########################################################################

run_experiment () {

        
    exp_name=$1
    start=$2
    end=$3
    rtime=$4
    stime=$5



    kill_stuff

    sudo service docker start
    
    echo $exp_name ' - DOCKER'
    sudo su -c 'touch /tmp/use_docker' hwcron
    sudo rm  /var/local/submitty/logs/autograding/20180220.txt
    /home/$(test_user)/performance_monitor.py /home/$(test_user)/PAPER_DATA/${exp_name}_DOCKER_performance.txt $rtime &
    /usr/local/submitty/bin/regrade.py --replay "${start}" "${end}"
    #/usr/local/submitty/bin/regrade.py --replay "${start}" "${end}"
    #/usr/local/submitty/bin/regrade.py --replay "${start}" "${end}"
    #/usr/local/submitty/bin/regrade.py --replay "${start}" "${end}"    
    sleep $((stime*60))
    python3 /usr/local/submitty/GIT_CHECKOUT/Submitty/bin/anonymize_autograding_logs.py file /var/local/submitty/logs/autograding/20180220.txt /home/$(test_user)/PAPER_DATA/${exp_name}_DOCKER_autograding_log.txt XX

    sudo chown $(test_user):$(test_user) /home/$(test_user)/PAPER_DATA/*



    

    
    kill_stuff

    sudo service docker stop
    
    echo $exp_name ' - no docker'
    sudo rm -f /tmp/use_docker 
    sudo rm  /var/local/submitty/logs/autograding/20180220.txt
    /home/$(test_user)/performance_monitor.py /home/$(test_user)/PAPER_DATA/${exp_name}_nodocker_performance.txt $rtime &
    /usr/local/submitty/bin/regrade.py --replay "${start}" "${end}"
    #/usr/local/submitty/bin/regrade.py --replay "${start}" "${end}"
    #/usr/local/submitty/bin/regrade.py --replay "${start}" "${end}"
    #/usr/local/submitty/bin/regrade.py --replay "${start}" "${end}"
    sleep $((stime*60))
    python3 /usr/local/submitty/GIT_CHECKOUT/Submitty/bin/anonymize_autograding_logs.py file /var/local/submitty/logs/autograding/20180220.txt /home/$(test_user)/PAPER_DATA/${exp_name}_nodocker_autograding_log.txt XX

    sudo chown $(test_user):$(test_user) /home/$(test_user)/PAPER_DATA/*


}
    

########################################################################





#high count - ds hw2
#run_experiment "A" '2017-09-13 14:59:00-0500' '2017-09-13 14:59:59-0500' 2 3
#run_experiment "A" '2017-09-13 14:00:00-0500' '2017-09-13 14:59:59-0500' 70 10

#echo 'LONG GRADING HOUR (B) - no docker'
#/var/local/submitty/logs/autograding/20171209.txt | hour 23 | total  416 other   13 |  wait  403   0.51 |  cs1_grade  362   6.03 | ds_grade   38 127.05
#run_experiment "B" '2017-12-09 23:00:00-0500' '2017-12-09 23:59:59-0500' 120 60
#run_experiment "B" '2017-12-09 23:59:00-0500' '2017-12-09 23:59:59-0500' 2 10



# long wait time

#run_experiment "C2" '2017-12-10 23:00:00-0500' '2017-12-10 23:59:59-0500' 90 30
#run_experiment "C" '2017-12-10 23:59:00-0500' '2017-12-10 23:59:59-0500' 2 1


#hw10
#/var/local/submitty/logs/autograding/20171210.txt | hour 22 | total  328 other   45 |  wait  283  39.91 |  cs1_grade  265   4.87 | ds_grade   19 142.37
#/var/local/submitty/logs/autograding/20171210.txt | hour 23 | total  312 other   34 |  wait  278  22.22 |  cs1_grade  242   5.64 | ds_grade   18 123.06

#hw8
#/var/local/submitty/logs/autograding/20171108.txt | hour 23 | total  141 other    4 |  wait  137  19.51 |  cs1_grade   67   6.90 | ds_grade   70  18.90



# 4 hours of hw6 replayed in 1 hour

#run_experiment "D" '2017-10-20 20:00:00-0500' '2017-10-20 23:59:59-0500' 90 30


#4 hours at 16X 4 times @ 20 core
#run_experiment "E" '2017-10-20 20:00:00-0500' '2017-10-20 23:59:59-0500' 90 30

#4 hours at 16X 4 times @ 35 processes
#run_experiment "F" '2017-10-20 20:00:00-0500' '2017-10-20 23:59:59-0500' 75 15

#4 hours at 16X 4 times @ 30 processes
run_experiment "G" '2017-10-20 20:00:00-0500' '2017-10-20 23:59:59-0500' 75 15


#/var/local/submitty/backups/logs/autograding/20171020.txt | hour 23 | total  244 other   45 |  wait  199   0.64 |  cs1_grade   94   4.74 | ds_grade  106  34.22



########################################################################







