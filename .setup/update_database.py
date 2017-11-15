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

today = datetime.today()
semester = "f" + str(today.year)[-2:]
if today.month < 7:
    semester = "s" + str(today.year)[-2:]
for course in os.scandir(os.path.join(settings['submitty_data_dir'], "courses", semester)):
    name = course.name
    db = "submitty_" + semester + "_" + name

    print ("updating course database " + db)

    # edits to migrate v.1.0.1 to v.1.0.2
    os.system("PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'ALTER TABLE ONLY gradeable_component_mark ADD COLUMN gcm_publish boolean DEFAULT false NOT NULL'".format(settings['database_password'], settings['database_host'], settings['database_user'], db))
    os.system("PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'ALTER TABLE ONLY electronic_gradeable ADD COLUMN eg_allow_late_submission boolean DEFAULT true NOT NULL'".format(settings['database_password'], settings['database_host'], settings['database_user'], db))
    os.system("PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'ALTER TABLE gradeable_component_data ALTER COLUMN gcd_grade_time SET DATA TYPE timestamp(6) with time zone'".format(settings['database_password'], settings['database_host'], settings['database_user'], db))
    os.system("PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'ALTER TABLE ONLY gradeable_component_mark_data DROP CONSTRAINT gradeable_component_mark_data_gd_id_and_gc_id_fkey'".format(settings['database_password'], settings['database_host'], settings['database_user'], db))
    os.system("PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'ALTER TABLE ONLY gradeable_component_mark_data ADD CONSTRAINT gradeable_component_mark_data_gd_id_and_gc_id_fkey FOREIGN KEY (gd_id, gc_id, gcd_grader_id) REFERENCES gradeable_component_data(gd_id, gc_id, gcd_grader_id) ON UPDATE CASCADE ON DELETE CASCADE'".format(settings['database_password'], settings['database_host'], settings['database_user'], db))

    # edits to migrate v.1.0.2 to v.1.0.3
    os.system("PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'ALTER TABLE gradeable_component ALTER COLUMN gc_lower_clamp SET DATA TYPE numeric NOT NULL'".format(settings['database_password'], settings['database_host'], settings['database_user'], db))
    os.system("PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'ALTER TABLE gradeable_component ALTER COLUMN gc_default SET DATA TYPE numeric NOT NULL'".format(settings['database_password'], settings['database_host'], settings['database_user'], db))
    os.system("PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'ALTER TABLE gradeable_component ALTER COLUMN gc_upper_clamp SET DATA TYPE numeric NOT NULL'".format(settings['database_password'], settings['database_host'], settings['database_user'], db))
    
    print ("\n")
    
