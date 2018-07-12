def up(config, conn, semester, course):
    with conn.cursor() as cursor:
        cursor.execute("CREATE TABLE gradeable_component_data (gc_id integer NOT NULL, gd_id integer NOT NULL, gcd_score numeric NOT NULL, gcd_component_comment character varying NOT NULL, gcd_grader_id character varying(255) NOT NULL, gcd_grader2_id character varying(255) NULL, gcd_graded_version integer, gcd_grade_time timestamp(6) with time zone NOT NULL)")

        cursor.execute("ALTER TABLE ONLY gradeable_component_data ADD CONSTRAINT gradeable_component_data_pkey PRIMARY KEY (gc_id, gd_id, gcd_grader_id)")
        cursor.execute("CREATE INDEX gradeable_component_data_no_grader_index ON gradeable_component_data (gc_id, gd_id)")
        cursor.execute("ALTER TABLE ONLY gradeable_component_data ADD CONSTRAINT gradeable_component_data_gc_id_fkey FOREIGN KEY (gc_id) REFERENCES gradeable_component(gc_id) ON DELETE CASCADE")
        cursor.execute("ALTER TABLE ONLY gradeable_component_data ADD CONSTRAINT gradeable_component_data_gd_id_fkey FOREIGN KEY (gd_id) REFERENCES gradeable_data(gd_id) ON DELETE CASCADE")
        cursor.execute("ALTER TABLE ONLY gradeable_component_data ADD CONSTRAINT gradeable_component_data_gcd_grader_id_fkey FOREIGN KEY (gcd_grader_id) REFERENCES users(user_id) ON UPDATE CASCADE")
        cursor.execute("ALTER TABLE ONLY gradeable_component_mark_data ADD CONSTRAINT gradeable_component_mark_data_gd_id_and_gc_id_fkey FOREIGN KEY (gd_id, gc_id, gcd_grader_id) REFERENCES gradeable_component_data(gd_id, gc_id, gcd_grader_id) ON UPDATE CASCADE ON DELETE CASCADE")

def down(config, conn, semester, course):
    with conn.cursor() as cursor:
    	cursor.execute("DROP TABLE gradeable_component_data CASCADE")