#!/usr/bin/env python3
#
# This script is run by a cron job as the DAEMON_USER
#
# Whenever a gradeable is built, insert testcase details into the database

from sqlalchemy import create_engine, Table, MetaData, bindparam, select, func, insert, delete, update

def insert_into_database():
