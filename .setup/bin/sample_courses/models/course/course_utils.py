"""
None of the functions should be imported here directly, but from
the class Course
"""
import os
from sample_courses import SETUP_DATA_PATH

class Course_utils:
    """
    Contains basic course utils functions
    """
    # global vars that are instantiated in Class course
    # This is only to type define the global vars to make it easier to debug using
    # intellisense
    semester:str
    # code:dict type unkown
    # instructor:dict type unkown
    gradeables:list
    make_customization:bool
    users: list
    registration_sections: int
    rotating_sections: int
    registered_students: int
    no_registration_sections: int
    no_rotating_students: int
    unregistered_students: int

    def __init__(self):
        # Anything that needs to be initialized goes here
        pass

    def check_rotating(self, users) -> None:
        for gradeable in self.gradeables:
            for grading_rotating in gradeable.grading_rotating:
                string = "Invalid user_id {} for rotating section for gradeable {}".format(
                    grading_rotating['user_id'], gradeable.id)
                if grading_rotating['user_id'] not in users:
                    raise ValueError(string)
                
    def getForumDataFromFile(self, filename) -> None:
        forum_path = os.path.join(SETUP_DATA_PATH, "forum")
        forum_data = []
        for line in open(os.path.join(forum_path, filename)):
            l = [x.replace("\\n", "\n").strip() for x in line.split("|")]
            if(len(line) > 1):
                forum_data.append(l)
        return forum_data

