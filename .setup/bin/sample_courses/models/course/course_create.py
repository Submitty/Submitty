"""
None of the functions should be imported here directly, but from
the class Course
"""
from __future__ import print_function, division
import hashlib
import json
import os
import os.path
import docker
import random


from sqlalchemy import create_engine, Table, MetaData, bindparam, insert, update

from sample_courses import (
    SUBMITTY_DATA_DIR,
    SUBMITTY_INSTALL_DIR,
    DB_HOST,
    DB_PORT,
    DB_USER,
    DB_PASS,
)

from sample_courses.utils.dependent import add_to_group
from sample_courses.utils.create_or_generate import create_group


class Course_create:
    """
    Contains the functions to create the course
    """

    semester: str
    # code: unknown type
    # instructor: unknown type
    gradeables: list
    make_customization: bool
    users: list
    registration_sections: int
    rotating_sections: int
    registered_students: int
    no_registration_sections: int
    no_rotating_students: int
    unregistered_students: int
    self_registration_type: int
    archived: bool

    def __init__(self) -> None:
        pass

    def create(self) -> None:
        # Sort users and gradeables in the name of determinism
        self.users.sort(key=lambda x: x.get_detail(self.code, "id"))
        self.gradeables.sort(key=lambda g: (g.depends_on is not None, g.id))
        self.course_path = os.path.join(
            SUBMITTY_DATA_DIR, "courses", self.semester, self.code
        )
        # To make Rainbow Grades testing possible, need to seed random
        m = hashlib.md5()
        m.update(bytes(self.code, "utf-8"))
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
        archive = ' --archive ' if self.archived else ''
        self_registration_type = ' --all-self-registration ' if self.self_registration_type == 2 else ''
        os.system(
            f"{SUBMITTY_INSTALL_DIR}/sbin/create_course.sh {self_registration_type} {archive} {self.semester} {self.code}"
            f" {self.instructor.id} {course_group}"
        )

        os.environ["PGPASSWORD"] = DB_PASS
        database = "submitty_" + self.semester + "_" + self.code
        print("Database created, now populating ", end="")

        submitty_engine = create_engine(
            f"postgresql:///submitty?host={DB_HOST}&port={DB_PORT}"
            f"&user={DB_USER}&password={DB_PASS}"
        )
        submitty_conn = submitty_engine.connect()
        submitty_metadata = MetaData()
        print("(Master DB connection made, metadata bound)...")

        engine = create_engine(
            f"postgresql:///{database}?host={DB_HOST}&port={DB_PORT}"
            f"&user={DB_USER}&password={DB_PASS}"
        )
        self.conn = engine.connect()
        # need to pass in engine to autoload tables
        self.engine = engine
        self.metadata = MetaData()
        print("(Course DB connection made, metadata bound)...")

        print("Creating registration sections ", end="")

        table = Table("courses_registration_sections", submitty_metadata, autoload_with=submitty_engine)
        print("(tables loaded)...")
        for section in range(1, self.registration_sections + 1):
            print(f"Create section {section}")
            submitty_conn.execute(
                insert(table).values(
                    term=self.semester,
                    course=self.code,
                    registration_section_id=str(section)
                )
            )
            submitty_conn.commit()
        table = Table("courses", submitty_metadata, autoload_with=submitty_engine)
        print("(tables loaded)...")
        if self.self_registration_type != 0:
            print("Setting course default section id to 1")
            submitty_conn.execute(
                update(table)
                .where(table.c.course == self.code)
                .values(default_section_id=1)
            )
            submitty_conn.commit()
        print("Creating rotating sections ", end="")
        table = Table("sections_rotating", self.metadata, autoload_with=self.engine)
        print("(tables loaded)...")
        for section in range(1, self.rotating_sections + 1):
            print(f"Create section {section}")
            self.conn.execute(insert(table).values(sections_rotating_id=section))
            self.conn.commit()

        print("Create users ", end="")
        submitty_users = Table("courses_users", submitty_metadata, autoload_with=submitty_engine)
        users_table = Table("users", self.metadata, autoload_with=self.engine)
        reg_table = Table("grading_registration", self.metadata, autoload_with=self.engine)
        print("(tables loaded)...")
        for user in self.users:
            print(
                f"Creating user {user.get_detail(self.code, 'givenname')} "
                f"{user.get_detail(self.code, 'familyname')} "
                f"({user.get_detail(self.code, 'id')})..."
            )
            reg_section = user.get_detail(self.code, "registration_section")
            if reg_section is not None and reg_section > self.registration_sections:
                reg_section = None
            rot_section = user.get_detail(self.code, "rotating_section")
            if rot_section is not None and rot_section > self.rotating_sections:
                rot_section = None
            if reg_section is not None:
                reg_section = str(reg_section)
            # We already have a row in submitty.users for this user,
            # just need to add a row in courses_users which will put a
            # a row in the course specific DB, and off we go.
            submitty_conn.execute(
                insert(submitty_users).values(
                    term=self.semester,
                    course=self.code,
                    user_id=user.get_detail(self.code, "id"),
                    user_group=user.get_detail(self.code, "group"),
                    registration_section=reg_section,
                    registration_type=user.get_detail(self.code, "registration_type"),
                    manual_registration=user.get_detail(self.code, "manual")
                )
            )
            submitty_conn.commit()
            update_query = update(users_table).where(
                users_table.c.user_id == bindparam("b_user_id")
            ).values(
                rotating_section=bindparam("rotating_section")
            )

            self.conn.execute(
                update_query,
                {"rotating_section": rot_section, "b_user_id": user.id}
            )
            self.conn.commit()
            if user.get_detail(self.code, "grading_registration_section") is not None:
                try:
                    grading_registration_sections = str(
                        user.get_detail(self.code, "grading_registration_section")
                    )
                    grading_registration_sections = [
                        int(x) for x in grading_registration_sections.split(",")
                    ]
                except ValueError:
                    grading_registration_sections = []
                for grading_registration_section in grading_registration_sections:
                    self.conn.execute(
                        insert(reg_table).values(
                            user_id=user.get_detail(self.code, "id"),
                            sections_registration_id=str(grading_registration_section),
                        )
                    )
                    self.conn.commit()

            if user.unix_groups is None:
                if user.get_detail(self.code, "group") <= 1:
                    add_to_group(self.code, user.id)
                    add_to_group(self.code + "_archive", user.id)
                if user.get_detail(self.code, "group") <= 2:
                    add_to_group(self.code + "_tas_www", user.id)

        self.gradeable_table = Table("gradeable", self.metadata, autoload_with=self.engine)
        self.electronic_table = Table(
            "electronic_gradeable", self.metadata, autoload_with=self.engine
        )
        self.peer_assign = Table("peer_assign", self.metadata, autoload_with=self.engine)
        self.reg_table = Table("grading_rotating", self.metadata, autoload_with=self.engine)
        self.component_table = Table(
            "gradeable_component", self.metadata, autoload_with=self.engine
        )
        self.mark_table = Table(
            "gradeable_component_mark", self.metadata, autoload_with=self.engine
        )
        self.gradeable_data = Table("gradeable_data", self.metadata, autoload_with=self.engine)
        self.gradeable_component_data = Table(
            "gradeable_component_data", self.metadata, autoload_with=self.engine
        )
        self.gradeable_component_mark_data = Table(
            "gradeable_component_mark_data", self.metadata, autoload_with=self.engine
        )
        self.gradeable_data_overall_comment = Table(
            "gradeable_data_overall_comment", self.metadata, autoload_with=self.engine
        )
        self.electronic_gradeable_data = Table(
            "electronic_gradeable_data", self.metadata, autoload_with=self.engine
        )
        self.electronic_gradeable_version = Table(
            "electronic_gradeable_version", self.metadata, autoload_with=self.engine
        )
        for gradeable in self.gradeables:
            gradeable.create(
                self.conn,
                self.gradeable_table,
                self.electronic_table,
                self.peer_assign,
                reg_table,
                self.component_table,
                self.mark_table,
            )
            form = os.path.join(
                self.course_path, "config", "form", f"form_{gradeable.id}.json"
            )
            with open(form, "w") as open_file:
                json.dump(gradeable.create_form(), open_file, indent=2)
        os.system(
            f"chown -f submitty_php:{self.code}_tas_www "
            f"{os.path.join(self.course_path, 'config', 'form', '*')}"
        )
        if not os.path.isfile(os.path.join(self.course_path, "ASSIGNMENTS.txt")):
            os.system(f"touch {os.path.join(self.course_path, 'ASSIGNMENTS.txt')}")
            os.system(
                f"chown {self.instructor.id}:{self.code}_tas_www "
                f"{os.path.join(self.course_path, 'ASSIGNMENTS.txt')}"
            )
            os.system(f"chmod -R g+w {self.course_path}")
            os.system(
                f"su {'submitty_daemon'} -c "
                f"'{ os.path.join(self.course_path,f'BUILD_{self.code}.sh')}'"
            )
        os.system(
            f"chown -R {self.instructor.id}:{self.code}_tas_www "
            f"{os.path.join(self.course_path, 'build')}"
        )
        os.system(
            f"chown -R {self.instructor.id}:{self.code}_tas_www "
            f"{os.path.join(self.course_path, 'test_*')}"
        )
        # On python 3, replace with os.makedirs(..., exist_ok=True)
        os.system(f"mkdir -p {os.path.join(self.course_path, 'submissions')}")
        os.system(
            f"chown submitty_php:{self.code}_tas_www "
            f"{os.path.join(self.course_path, 'submissions')}"
        )

        self.add_gradeables()
        self.conn.close()
        submitty_conn.close()
        os.environ["PGPASSWORD"] = ""

        if self.code == "tutorial":
            client = docker.from_env()
            client.images.pull("submitty/tutorial:tutorial_18")
            client.images.pull("submitty/tutorial:database_client")
