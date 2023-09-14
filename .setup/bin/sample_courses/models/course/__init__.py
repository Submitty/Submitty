# flake8: noqa
from __future__ import print_function, division
import hashlib
import json
import os
import random
import os.path
import docker
import random


from sqlalchemy import create_engine, Table, MetaData, bindparam

from sample_courses import *
from sample_courses.utils import (
    get_current_semester
)
from sample_courses.utils.dependent import add_to_group
from sample_courses.utils.create_or_generate import create_group
from sample_courses.models.gradeable import Gradeable
from sample_courses.models.course.course_generate_utils import Course_generate_utils
from sample_courses.models.course.course_create_gradeables import Course_create_gradeables
from sample_courses.models.course.course_utils import Course_utils
from sample_courses.models.course.course_data import Course_data
from sample_courses.models.course.global_var import Table_var

class Course(Course_generate_utils,Course_create_gradeables, Course_utils, Course_data):
    """
    Object to represent the courses loaded from the courses.json file as well as the list of
    users that are needed for this particular course (which is a list of User objects).

    Attributes:
        code
        semester
        instructor
        gradeables
        users
        max_random_submissions
    """
    def __init__(self, course) -> None:
        # Using super() to call the contructor will only run the first init in the parent class
        # Nothing is currently running in the init of both of these classes
        # but if anything is placed in the init of these classes then it will run
        Course_generate_utils.__init__(self)
        Course_create_gradeables.__init__(self)
        Course_data.__init__(self)
        Course_utils.__init__(self)

        #Sets the global values (Should be placed in course_utils)
        self.semester: str = get_current_semester()
        self.code = course['code']
        self.instructor = course['instructor']
        self.gradeables: list = []
        self.make_customization: bool = False
        ids = []
        if 'gradeables' in course:
            for gradeable in course['gradeables']:
                self.gradeables.append(Gradeable(gradeable))
                assert self.gradeables[-1].id not in ids
                ids.append(self.gradeables[-1].id)
        self.users: list = []
        self.registration_sections: int = 10
        self.rotating_sections: int = 5
        self.registered_students: int = 50
        self.no_registration_students: int = 10
        self.no_rotating_students: int = 10
        self.unregistered_students: int = 10
        if 'registration_sections' in course:
            self.registration_sections = course['registration_sections']
        if 'rotating_sections' in course:
            self.rotating_sections = course['rotating_sections']
        if 'registered_students' in course:
            self.registered_students = course['registered_students']
        if 'no_registration_students' in course:
            self.no_registration_students = course['no_registration_students']
        if 'no_rotating_students' in course:
            self.no_rotating_students = course['no_rotating_students']
        if 'unregistered_students' in course:
            self.unregistered_students = course['unregistered_students']
        if 'make_customization' in course:
            self.make_customization = course['make_customization']

    def create(self):
        # Sort users and gradeables in the name of determinism
        self.users.sort(key=lambda x: x.get_detail(self.code, "id"))
        self.gradeables.sort(key=lambda x: x.id)
        self.course_path = os.path.join(SUBMITTY_DATA_DIR, "courses", self.semester, self.code)
        # To make Rainbow Grades testing possible, need to seed random
        m = hashlib.md5()
        m.update(bytes(self.code, 'utf-8'))
        random.seed(int(m.hexdigest(), 16))

        course_group = self.code + "_tas_www"
        archive_group = self.code + "_archive"
        create_group(self.code)
        create_group(course_group)
        create_group(archive_group)
        add_to_group(self.code, self.instructor.id)
        add_to_group(course_group, self.instructor.id)
        add_to_group(archive_group, self.instructor.id)
        add_to_group("submitty_course_builders", self.instructor.id)
        add_to_group(course_group, "submitty_php")
        add_to_group(course_group, "submitty_daemon")
        add_to_group(course_group, "submitty_cgi")
        os.system("{}/sbin/create_course.sh {} {} {} {}"
                  .format(SUBMITTY_INSTALL_DIR, self.semester, self.code, self.instructor.id,
                          course_group))

        os.environ['PGPASSWORD'] = DB_PASS
        database = "submitty_" + self.semester + "_" + self.code
        print("Database created, now populating ", end="")

        submitty_engine = create_engine("postgresql:///submitty?host={}&port={}&user={}&password={}"
                                        .format(DB_HOST, DB_PORT, DB_USER, DB_PASS))
        submitty_conn = submitty_engine.connect()
        submitty_metadata = MetaData(bind=submitty_engine)
        print("(Master DB connection made, metadata bound)...")

        engine = create_engine("postgresql:///{}?host={}&port={}&user={}&password={}"
                               .format(database, DB_HOST, DB_PORT, DB_USER, DB_PASS))
        self.conn = engine.connect()
        self.metadata = MetaData(bind=engine)
        print("(Course DB connection made, metadata bound)...")

        print("Creating registration sections ", end="")
        table_var = Table_var()
        table = Table("courses_registration_sections", submitty_metadata, autoload=True)
        print("(tables loaded)...")
        for section in range(1, self.registration_sections+1):
            print("Create section {}".format(section))
            submitty_conn.execute(table.insert(), term=self.semester, course=self.code, registration_section_id=str(section))

        print("Creating rotating sections ", end="")
        table = Table("sections_rotating", self.metadata, autoload=True)
        print("(tables loaded)...")
        for section in range(1, self.rotating_sections+1):
            print("Create section {}".format(section))
            self.conn.execute(table.insert(), sections_rotating_id=section)

        print("Create users ", end="")
        submitty_users = Table("courses_users", submitty_metadata, autoload=True)
        users_table = Table("users", self.metadata, autoload=True)
        reg_table = Table("grading_registration", self.metadata, autoload=True)
        print("(tables loaded)...")
        for user in self.users:
            print("Creating user {} {} ({})...".format(user.get_detail(self.code, "givenname"),
                                                       user.get_detail(self.code, "familyname"),
                                                       user.get_detail(self.code, "id")))
            reg_section = user.get_detail(self.code, "registration_section")
            if reg_section is not None and reg_section > self.registration_sections:
                reg_section = None
            rot_section = user.get_detail(self.code, "rotating_section")
            if rot_section is not None and rot_section > self.rotating_sections:
                rot_section = None
            if reg_section is not None:
                reg_section=str(reg_section)
            # We already have a row in submitty.users for this user,
            # just need to add a row in courses_users which will put a
            # a row in the course specific DB, and off we go.
            submitty_conn.execute(submitty_users.insert(),
                                  term=self.semester,
                                  course=self.code,
                                  user_id=user.get_detail(self.code, "id"),
                                  user_group=user.get_detail(self.code, "group"),
                                  registration_section=reg_section,
                                  manual_registration=user.get_detail(self.code, "manual"))
            update = users_table.update(values={
                users_table.c.rotating_section: bindparam('rotating_section')
            }).where(users_table.c.user_id == bindparam('b_user_id'))

            self.conn.execute(update, rotating_section=rot_section, b_user_id=user.id)
            if user.get_detail(self.code, "grading_registration_section") is not None:
                try:
                    grading_registration_sections = str(user.get_detail(self.code,"grading_registration_section"))
                    grading_registration_sections = [int(x) for x in grading_registration_sections.split(",")]
                except ValueError:
                    grading_registration_sections = []
                for grading_registration_section in grading_registration_sections:
                    self.conn.execute(reg_table.insert(),
                                 user_id=user.get_detail(self.code, "id"),
                                 sections_registration_id=str(grading_registration_section))

            if user.unix_groups is None:
                if user.get_detail(self.code, "group") <= 1:
                    add_to_group(self.code, user.id)
                    add_to_group(self.code + "_archive", user.id)
                if user.get_detail(self.code, "group") <= 2:
                    add_to_group(self.code + "_tas_www", user.id)
        
        table_var.gradeable_table = Table("gradeable", self.metadata, autoload=True)
        table_var.electronic_table = Table("electronic_gradeable", self.metadata, autoload=True)
        table_var.peer_assign = Table("peer_assign", self.metadata, autoload=True)
        table_var.reg_table = Table("grading_rotating", self.metadata, autoload=True)
        table_var.component_table = Table('gradeable_component', self.metadata, autoload=True)
        table_var.mark_table = Table('gradeable_component_mark', self.metadata, autoload=True)
        table_var.gradeable_data = Table("gradeable_data", self.metadata, autoload=True)
        table_var.gradeable_component_data = Table("gradeable_component_data", self.metadata, autoload=True)
        table_var.gradeable_component_mark_data = Table('gradeable_component_mark_data', self.metadata, autoload=True)
        table_var.gradeable_data_overall_comment = Table('gradeable_data_overall_comment', self.metadata, autoload=True)
        table_var.electronic_gradeable_data = Table("electronic_gradeable_data", self.metadata, autoload=True)
        table_var.electronic_gradeable_version = Table("electronic_gradeable_version", self.metadata, autoload=True)
        for gradeable in self.gradeables:
            gradeable.create(self.conn, table_var.gradeable_table, table_var.electronic_table, table_var.peer_assign, reg_table, table_var.component_table, table_var.mark_table)
            form = os.path.join(self.course_path, "config", "form", "form_{}.json".format(gradeable.id))
            with open(form, "w") as open_file:
                json.dump(gradeable.create_form(), open_file, indent=2)
        os.system("chown -f submitty_php:{}_tas_www {}".format(self.code, os.path.join(self.course_path, "config", "form", "*")))
        if not os.path.isfile(os.path.join(self.course_path, "ASSIGNMENTS.txt")):
            os.system("touch {}".format(os.path.join(self.course_path, "ASSIGNMENTS.txt")))
            os.system("chown {}:{}_tas_www {}".format(self.instructor.id, self.code,
                                                      os.path.join(self.course_path, "ASSIGNMENTS.txt")))
            os.system("chmod -R g+w {}".format(self.course_path))
            os.system("su {} -c '{}'".format("submitty_daemon", os.path.join(self.course_path,
                                                                          "BUILD_{}.sh".format(self.code))))
            #os.system("su {} -c '{}'".format(self.instructor.id, os.path.join(self.course_path,
            #                                                              "BUILD_{}.sh".format(self.code))))
        os.system("chown -R {}:{}_tas_www {}".format(self.instructor.id, self.code, os.path.join(self.course_path, "build")))
        os.system("chown -R {}:{}_tas_www {}".format(self.instructor.id, self.code,
                                                     os.path.join(self.course_path, "test_*")))
        # On python 3, replace with os.makedirs(..., exist_ok=True)
        os.system("mkdir -p {}".format(os.path.join(self.course_path, "submissions")))
        os.system('chown submitty_php:{}_tas_www {}'.format(self.code, os.path.join(self.course_path, 'submissions')))

        self.add_gradeables(table_var)
        self.conn.close()
        submitty_conn.close()
        os.environ['PGPASSWORD'] = ""

        if self.code == 'tutorial':
            client = docker.from_env()
            client.images.pull('submitty/tutorial:tutorial_18')
            client.images.pull('submitty/tutorial:database_client')
