"""
Contains the Mark class and User class
"""
from pathlib import Path
import random
import os
from sample_courses import SETUP_DATA_PATH, DB_ONLY, yaml
from sample_courses.utils.create_or_generate import (
    generate_random_user_id,
    generate_pronouns,
)
from sample_courses.utils.checks import user_exists
from sample_courses.utils.dependent import add_to_group
from sqlalchemy import insert

class Mark(object):
    def __init__(self, mark, order) -> None:
        self.note = mark["gcm_note"]
        self.points = mark["gcm_points"]
        self.order = order
        self.grader = "instructor"
        self.key = None

    def create(self, gc_id, conn, table) -> None:
        ins = insert(table).values(
            gc_id=gc_id,
            gcm_points=self.points,
            gcm_note=self.note,
            gcm_order=self.order,
        )
        res = conn.execute(ins)
        conn.commit()
        self.key = res.inserted_primary_key[0]


def generate_random_marks(default_value, max_value) -> list:
    with open(os.path.join(SETUP_DATA_PATH, "random", "marks.yml")) as f:
        marks_yml = yaml.load(f)
    if default_value == max_value and default_value > 0:
        key = "count_down"
    else:
        key = "count_up"
    marks = []
    mark_list = random.choice(marks_yml[key])
    for i in range(len(mark_list)):
        marks.append(Mark(mark_list[i], i))
    return marks


class User(object):
    """
    A basic object to contain the objects loaded from the users.json file. We use this to link
    against the courses.

    Attributes:
        id
        numeric_id
        password
        givenname
        familyname
        pronouns
        email
        group
        preferred_givenname
        preferred_familyname
        access_level
        registration_section
        rotating_section
        unix_groups
        courses
    """

    def __init__(self, user) -> None:
        self.id = user["user_id"]
        self.numeric_id = user["user_numeric_id"]
        self.password = self.id
        self.givenname = user["user_givenname"]
        self.familyname = user["user_familyname"]
        self.pronouns = user["user_pronouns"]
        self.email = self.id + "@example.com"
        self.group = 4
        self.preferred_givenname = None
        self.preferred_familyname = None
        self.access_level = 3
        self.registration_section = None
        self.rotating_section = None
        self.grading_registration_section = None
        self.unix_groups = None
        self.courses = None
        self.manual = False
        self.sudo = False

        if "user_preferred_givenname" in user:
            self.preferred_givenname = user["user_preferred_givenname"]
        if "user_preferred_familyname" in user:
            self.preferred_familyname = user["user_preferred_familyname"]
        if "user_email" in user:
            self.email = user["user_email"]
        if "user_group" in user:
            self.group = user["user_group"]
        if self.group < 1 or 4 < self.group:
            raise SystemExit(
                f"ASSERT: user {self.id}, user_group is not between 1 - 4. Check YML file."
            )
        if "user_access_level" in user:
            self.access_level = user["user_access_level"]
        if self.access_level < 1 or 3 < self.access_level:
            raise SystemExit(
                f"ASSERT: user {self.id}, user_access_level is not between 1 - 3. Check YML file."
            )
        if "registration_section" in user:
            self.registration_section = int(user["registration_section"])
        if "rotating_section" in user:
            self.rotating_section = int(user["rotating_section"])
        if "grading_registration_section" in user:
            self.grading_registration_section = user["grading_registration_section"]
        if "unix_groups" in user:
            self.unix_groups = user["unix_groups"]
        if "manual_registration" in user:
            self.manual = user["manual_registration"] is True
        if "courses" in user:
            self.courses = {}
            if isinstance(user["courses"], list):
                for course in user["courses"]:
                    self.courses[course] = {"user_group": self.group}
            elif isinstance(user["courses"], dict):
                self.courses = user["courses"]
                for course in self.courses:
                    if "user_group" not in self.courses[course]:
                        self.courses[course]["user_group"] = self.group
            else:
                raise ValueError(
                    "Invalid type for courses key, it should either be list or dict"
                )
        if "sudo" in user:
            self.sudo = user["sudo"] is True
        if "user_password" in user:
            self.password = user["user_password"]

    def create(self, force_ssh=False) -> None:
        if not DB_ONLY and not user_exists(self.id):
            if self.group > 2 and not force_ssh:
                self.create_non_ssh()
            else:
                self.create_ssh()
            self.create_ldap()

        if self.group <= 1:
            add_to_group("submitty_course_builders", self.id)
        if self.sudo:
            add_to_group("sudo", self.id)

    def create_ssh(self) -> None:
        print(f"Creating user {self.id}...")

        os.system(
            f"useradd -m -c 'First Last,RoomNumber,WorkPhone,HomePhone' {self.id}"
        )
        self.set_password()

    def create_non_ssh(self) -> None:
        # Change this to f strings
        print(f"Creating user {self.id}...")
        os.system(
            "useradd --home /tmp -c 'AUTH ONLY account' "
            f"-M --shell /bin/false {self.id}"
        )
        self.set_password()

    def create_ldap(self) -> None:
        print(f"Creating LDAP user {self.id}...")
        path = Path("/tmp", self.id)
        path.write_text(
            f"""
dn: uid={self.id},ou=users,dc=vagrant,dc=local
objectClass: top
objectClass: account
objectClass: shadowAccount
uid: {self.id}
userPassword: {self.id}
shadowLastChange: 0
shadowMax: 0
shadowWarning: 0"""
        )
        os.system(
            f'ldapadd -x -w root_password -D "cn=admin,dc=vagrant,dc=local" -f {path}'
        )
        path.unlink()

    def set_password(self) -> None:
        print(f"Setting password for user {self.id}...")
        os.system(f"echo {self.id}:{self.password} | chpasswd")

    def get_detail(self, course, detail):
        if self.courses is not None and course in self.courses:
            user_detail = "user_" + detail
            if user_detail in self.courses[course]:
                return self.courses[course][user_detail]
            elif detail in self.courses[course]:
                return self.courses[course][detail]
        if detail in self.__dict__:
            return self.__dict__[detail]
        else:
            return None


def generate_random_users(total, real_users) -> list:
    """
    :param total:
    :param real_users:
    :return:
    :rtype: list[User]
    """
    with open(
        os.path.join(SETUP_DATA_PATH, "random", "familyNames.txt")
    ) as family_file, open(
        os.path.join(SETUP_DATA_PATH, "random", "maleGivenNames.txt")
    ) as male_file, open(
        os.path.join(SETUP_DATA_PATH, "random", "womenGivenNames.txt")
    ) as woman_file:
        family_names = family_file.read().strip().split()
        male_names = male_file.read().strip().split()
        women_names = woman_file.read().strip().split()

    users = []
    user_ids = []
    anon_ids = []
    with open(
        os.path.join(SETUP_DATA_PATH, "random_users.txt"), "w"
    ) as random_users_file:
        for i in range(total):
            if random.random() < 0.5:
                given_name = random.choice(male_names)
            else:
                given_name = random.choice(women_names)
            family_name = random.choice(family_names)
            user_id = family_name.replace("'", "")[:5] + given_name[0]
            user_id = user_id.lower()
            anon_id = generate_random_user_id(15)
            # create a binary string for the numeric ID
            numeric_id = f"{i:09b}"
            while user_id in user_ids or user_id in real_users:
                if user_id[-1].isdigit():
                    user_id = user_id[:-1] + str(int(user_id[-1]) + 1)
                else:
                    user_id = user_id + "1"
            if anon_id in anon_ids:
                anon_id = generate_random_user_id()
            new_user = User(
                {
                    "user_id": user_id,
                    "user_numeric_id": numeric_id,
                    "user_givenname": given_name,
                    "user_familyname": family_name,
                    "user_pronouns": generate_pronouns(),
                    "user_group": 4,
                    "courses": dict(),
                }
            )
            new_user.create()
            user_ids.append(user_id)
            users.append(new_user)
            anon_ids.append(anon_id)
            random_users_file.write(user_id + "\n")
    return users
