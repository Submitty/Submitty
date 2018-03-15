#!/usr/bin/env python3

#
# Run this file to upgrade all course databases from v.1.0.1 to v.1.0.2 (November 2017)
#
#!/usr/bin/env python3

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
import urllib.parse

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
        
        print ("updating course database " + db)

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

        # edits to migrate forum
        os.system("""PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'CREATE TABLE "viewed_responses" ("thread_id" int NOT NULL, "user_id" character varying NOT NULL, "timestamp" timestamp with time zone NOT NULL)'""".format(settings['database_password'], settings['database_host'], settings['database_user'], db))
        os.system("""PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'CREATE TABLE "student_favorites" ("id" serial NOT NULL, "user_id" character varying NOT NULL, "thread_id" int, CONSTRAINT student_favorites_pk PRIMARY KEY ("id"))'""".format(settings['database_password'], settings['database_host'], settings['database_user'], db))
        os.system("""PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'CREATE TABLE "categories_list" ("category_id" serial NOT NULL,"category_desc" varchar NOT NULL,CONSTRAINT categories_list_pk PRIMARY KEY ("category_id"))'""".format(settings['database_password'], settings['database_host'], settings['database_user'], db))
        os.system("""PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'CREATE TABLE "thread_categories" ("thread_id" int NOT NULL, "category_id" int NOT NULL)'""".format(settings['database_password'], settings['database_host'], settings['database_user'], db))
        os.system("""PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'CREATE TABLE "threads" ("id" serial NOT NULL,"title" varchar NOT NULL,"created_by" varchar NOT NULL,"pinned" BOOLEAN NOT NULL DEFAULT 'false',"deleted" BOOLEAN NOT NULL DEFAULT 'false',"merged_id" int DEFAULT '-1',"is_visible" BOOLEAN NOT NULL,CONSTRAINT threads_pk PRIMARY KEY ("id"))'""".format(settings['database_password'], settings['database_host'], settings['database_user'], db))
        os.system("""PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'CREATE TABLE "posts" ("id" serial NOT NULL,"thread_id" int NOT NULL,"parent_id" int DEFAULT '-1',"author_user_id" character varying NOT NULL,"content" TEXT NOT NULL,"timestamp" timestamp with time zone NOT NULL,"anonymous" BOOLEAN NOT NULL,"deleted" BOOLEAN NOT NULL DEFAULT 'false',"endorsed_by" varchar,"resolved" BOOLEAN NOT NULL,"type" int NOT NULL, "has_attachment" BOOLEAN NOT NULL, CONSTRAINT posts_pk PRIMARY KEY ("id"))'""".format(settings['database_password'], settings['database_host'], settings['database_user'], db))

        os.system("""PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'ALTER TABLE "posts" ADD CONSTRAINT "posts_fk0" FOREIGN KEY ("thread_id") REFERENCES "threads"("id")'""".format(settings['database_password'], settings['database_host'], settings['database_user'], db))
        os.system("""PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'ALTER TABLE "posts" ADD CONSTRAINT "posts_fk1" FOREIGN KEY ("author_user_id") REFERENCES "users"("user_id")'""".format(settings['database_password'], settings['database_host'], settings['database_user'], db))
    
        os.system("""PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'ALTER TABLE "thread_categories" ADD CONSTRAINT "thread_categories_fk0" FOREIGN KEY ("thread_id") REFERENCES "threads"("id")'""".format(settings['database_password'], settings['database_host'], settings['database_user'], db))
        os.system("""PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'ALTER TABLE "thread_categories" ADD CONSTRAINT "thread_categories_fk1" FOREIGN KEY ("category_id") REFERENCES "categories_list"("category_id")'""".format(settings['database_password'], settings['database_host'], settings['database_user'], db))
        os.system("""PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'ALTER TABLE "student_favorites" ADD CONSTRAINT "student_favorites_fk0" FOREIGN KEY ("user_id") REFERENCES "users"("user_id")'""".format(settings['database_password'], settings['database_host'], settings['database_user'], db))

        os.system("""PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'ALTER TABLE "student_favorites" ADD CONSTRAINT "student_favorites_fk1" FOREIGN KEY ("thread_id") REFERENCES "threads"("id")'""".format(settings['database_password'], settings['database_host'], settings['database_user'], db))
        os.system("""PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'ALTER TABLE "viewed_responses" ADD CONSTRAINT "viewed_responses_fk0" FOREIGN KEY ("thread_id") REFERENCES "threads"("id")'""".format(settings['database_password'], settings['database_host'], settings['database_user'], db))
        os.system("""PGPASSWORD='{}' psql --host={} --username={} --dbname={} -c 'ALTER TABLE "viewed_responses" ADD CONSTRAINT "viewed_responses_fk1" FOREIGN KEY ("user_id") REFERENCES "users"("user_id")'""".format(settings['database_password'], settings['database_host'], settings['database_user'], db))
    

        # create the forum attachments directory and set the owner, group, and permissions
        course_dir = os.path.join(settings['submitty_data_dir'],"courses",term.name,course.name)
        forum_dir = os.path.join(course_dir,"forum_attachments")
        if not os.path.exists(forum_dir):
            os.makedirs(forum_dir)
            stat_info = os.stat(course_dir)
            uid = stat_info.st_uid
            gid = stat_info.st_gid
            os.chown(forum_dir,uid,gid)
            os.chmod(forum_dir,0o770)
            print ("created directory:" + forum_dir)
        else:
            #Legacy fix for spaces in attachment file names for the forum
            for root, dir_cur, files in os.walk(forum_dir):
                for filename in files:
                    os.rename(os.path.join(root, filename), os.path.join(root, urllib.parse.unquote(filename)));


            
        print ("\n")
