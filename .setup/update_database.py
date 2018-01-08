#!/usr/bin/env python3

#
# Run this file to upgrade all course databases from v.1.0.1 to v.1.0.2 (November 2017)
#
# Changes:
#  v.1.0.2 
#    Adds column gcm_publish to the gradeable_component_mark table
#    Adds column eg_allow_late_submission to the electronic gradeable table
#    Add timezone to the type of gcd_grade_time in the gradeable_component_data table
#    Modify foreign key cascade constraint on gradeable_component_mark_data table
#  v.1.0.3
#    Switch component fields to allow floating point (not just integers)
#

from datetime import datetime
import json
import os

usr_path = "/usr/local/submitty"

settings = json.load(open(os.path.join(usr_path, ".setup", "submitty_conf.json")))


# ==============================
# edits to the master database

# edits to make timestamps consistent
os.system("PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'ALTER TABLE sessions ALTER COLUMN session_expires SET DATA TYPE timestamp(6) with time zone'".format(settings['database_password'], settings['database_host'], settings['database_user'], 'submitty'))
os.system("PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'ALTER TABLE users ALTER COLUMN last_updated SET DATA TYPE timestamp(6) with time zone'".format(settings['database_password'], settings['database_host'], settings['database_user'], 'submitty'))

# ==============================
# edits to each course database
for term in os.scandir(os.path.join(settings['submitty_data_dir'],"courses")):
    for course in os.scandir(os.path.join(settings['submitty_data_dir'], "courses", term.name)):
        name = course.name
        db = "submitty_" + term.name + "_" + name
        
        print ("updating course database " + db + "\n")

        # edits to migrate v.1.0.1 to v.1.0.2
        os.system("PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'ALTER TABLE ONLY gradeable_component_mark ADD COLUMN gcm_publish boolean DEFAULT false NOT NULL'".format(settings['database_password'], settings['database_host'], settings['database_user'], db))
        os.system("PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'ALTER TABLE ONLY electronic_gradeable ADD COLUMN eg_allow_late_submission boolean DEFAULT true NOT NULL'".format(settings['database_password'], settings['database_host'], settings['database_user'], db))
        os.system("PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'ALTER TABLE gradeable_component_data ALTER COLUMN gcd_grade_time SET DATA TYPE timestamp(6) with time zone'".format(settings['database_password'], settings['database_host'], settings['database_user'], db))
        os.system("PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'ALTER TABLE ONLY gradeable_component_mark_data DROP CONSTRAINT gradeable_component_mark_data_gd_id_and_gc_id_fkey'".format(settings['database_password'], settings['database_host'], settings['database_user'], db))
        os.system("PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'ALTER TABLE ONLY gradeable_component_mark_data ADD CONSTRAINT gradeable_component_mark_data_gd_id_and_gc_id_fkey FOREIGN KEY (gd_id, gc_id, gcd_grader_id) REFERENCES gradeable_component_data(gd_id, gc_id, gcd_grader_id) ON UPDATE CASCADE ON DELETE CASCADE'".format(settings['database_password'], settings['database_host'], settings['database_user'], db))

        # edits to migrate v.1.0.2 to v.1.0.3
        os.system("PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'ALTER TABLE gradeable_component ALTER COLUMN gc_lower_clamp SET DATA TYPE numeric'".format(settings['database_password'], settings['database_host'], settings['database_user'], db))
        os.system("PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'ALTER TABLE gradeable_component ALTER COLUMN gc_default SET DATA TYPE numeric'".format(settings['database_password'], settings['database_host'], settings['database_user'], db))
        os.system("PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'ALTER TABLE gradeable_component ALTER COLUMN gc_upper_clamp SET DATA TYPE numeric'".format(settings['database_password'], settings['database_host'], settings['database_user'], db))

        # edits to make timestamps consistent
        os.system("PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'ALTER TABLE late_days ALTER COLUMN since_timestamp SET DATA TYPE timestamp(6) with time zone'".format(settings['database_password'], settings['database_host'], settings['database_user'], db))
        os.system("PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'ALTER TABLE sessions ALTER COLUMN session_expires SET DATA TYPE timestamp(6) with time zone'".format(settings['database_password'], settings['database_host'], settings['database_user'], db))
        os.system("PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'ALTER TABLE users ALTER COLUMN last_updated SET DATA TYPE timestamp(6) with time zone'".format(settings['database_password'], settings['database_host'], settings['database_user'], db))
