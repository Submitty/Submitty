#!/usr/bin/env bash

#PATHS
SUBMITTY_DIR=/usr/local/hss/GIT_CHECKOUT_Submitty
INSTALL_DIR=/usr/local/hss
DATA_DIR=/var/local/hss



#################################################################
# COURSE SETUP
#################
cd ${INSTALL_DIR}/bin
./create_course.sh f15 csci1100 instructor csci1100_tas_www
./create_course.sh f15 csci1200 instructor csci1200_tas_www
./create_course.sh f15 csci2600 instructor csci2600_tas_www

cd ${DATA_DIR}/courses/f15/csci1100
./BUILD_csci1100.sh

cd ${DATA_DIR}/courses/f15/csci1200
./BUILD_csci1200.sh

cd ${DATA_DIR}/courses/f15/csci2600
./BUILD_csci2600.sh



#################################################################
# CREATE DATABASE
#################

export PGPASSWORD="hsdbu"

psql -d postgres -h localhost -U hsdbu -c "CREATE DATABASE hss_csci1100_f15;"
psql -d postgres -h localhost -U hsdbu -c "CREATE DATABASE hss_csci1200_f15;"
psql -d postgres -h localhost -U hsdbu -c "CREATE DATABASE hss_csci2600_f15;"

psql -d hss_csci1100_f15 -h localhost -U hsdbu -f ${SUBMITTY_DIR}/TAGradingServer/data/tables.sql
psql -d hss_csci1100_f15 -h localhost -U hsdbu -f ${SUBMITTY_DIR}/TAGradingServer/data/inserts.sql
psql -d hss_csci1200_f15 -h localhost -U hsdbu -f ${SUBMITTY_DIR}/TAGradingServer/data/tables.sql
psql -d hss_csci1200_f15 -h localhost -U hsdbu -f ${SUBMITTY_DIR}/TAGradingServer/data/inserts.sql
psql -d hss_csci2600_f15 -h localhost -U hsdbu -f ${SUBMITTY_DIR}/TAGradingServer/data/tables.sql
psql -d hss_csci2600_f15 -h localhost -U hsdbu -f ${SUBMITTY_DIR}/TAGradingServer/data/inserts.sql

psql -d hss_csci1100_f15 -h localhost -U hsdbu -f ${SUBMITTY_DIR}/.setup/vagrant/db_inserts.sql
psql -d hss_csci1200_f15 -h localhost -U hsdbu -f ${SUBMITTY_DIR}/.setup/vagrant/db_inserts.sql
psql -d hss_csci2600_f15 -h localhost -U hsdbu -f ${SUBMITTY_DIR}/.setup/vagrant/db_inserts.sql

