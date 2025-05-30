"""
Contain all the global variables and init
Only import this class
"""
from sqlalchemy import Table

from sample_courses.utils import get_current_semester
from sample_courses.models.gradeable import Gradeable
from sample_courses.models.course.course_create import Course_create
from sample_courses.models.course.course_generate_utils import Course_generate_utils
from sample_courses.models.course.course_create_gradeables import (
    Course_create_gradeables,
)
from sample_courses.models.course.course_utils import Course_utils
from sample_courses.models.course.course_data import Course_data


class Course(
    Course_create,
    Course_generate_utils,
    Course_create_gradeables,
    Course_utils,
    Course_data,
):
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
        Course_create.__init__(self)
        Course_generate_utils.__init__(self)
        Course_create_gradeables.__init__(self)
        Course_data.__init__(self)
        Course_utils.__init__(self)

        # Sets the global values
        self.gradeable_table: Table
        self.electronic_table: Table
        self.peer_assign: Table
        self.reg_table: Table
        self.component_table: Table
        self.mark_table: Table
        self.gradeable_data: Table
        self.gradeable_component_data: Table
        self.gradeable_component_mark_data: Table
        self.gradeable_data_overall_comment: Table
        self.electronic_gradeable_data: Table
        self.electronic_gradeable_version: Table
        self.semester: str = get_current_semester()
        self.code = course["code"]
        self.instructor = course["instructor"]
        self.gradeables: list = []
        self.make_customization: bool = False
        ids = []
        if "gradeables" in course:
            for gradeable in course["gradeables"]:
                self.gradeables.append(Gradeable(gradeable))
                # print(self.gradeables[-1].id, ids, [x.id for x in self.gradeables])
                assert self.gradeables[-1].id not in ids
                ids.append(self.gradeables[-1].id)
        self.users: list = []
        self.registration_sections: int = 10
        self.rotating_sections: int = 5
        self.registered_students: int = 50
        self.no_registration_students: int = 10
        self.no_rotating_students: int = 10
        self.unregistered_students: int = 10
        self.self_registration_type: int = 0
        self.archived: bool = False
        if "archived" in course:
            self.archived = course["archived"]
        if "registration_sections" in course:
            self.registration_sections = course["registration_sections"]
        if "rotating_sections" in course:
            self.rotating_sections = course["rotating_sections"]
        if "registered_students" in course:
            self.registered_students = course["registered_students"]
        if "no_registration_students" in course:
            self.no_registration_students = course["no_registration_students"]
        if "no_rotating_students" in course:
            self.no_rotating_students = course["no_rotating_students"]
        if "unregistered_students" in course:
            self.unregistered_students = course["unregistered_students"]
        if "make_customization" in course:
            self.make_customization = course["make_customization"]
        if "self_registration_type" in course:
            self.self_registration_type = course["self_registration_type"]
