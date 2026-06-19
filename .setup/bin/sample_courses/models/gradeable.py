"""
Contains the gradeable class with functions:
- create()
- create_form()

"""

from __future__ import print_function, division
from collections import OrderedDict
import hashlib
import os
import os.path
import random

from sqlalchemy import insert
from submitty_utils import dateutils

# if you need to modify any global variables, change this to import file as name
from sample_courses import MORE_EXAMPLES_DIR, SETUP_DATA_PATH, TUTORIAL_DIR
from sample_courses.utils import load_data_yaml
from sample_courses.utils.create_or_generate import (
    generate_random_ta_note,
    generate_random_student_note,
)
from sample_courses.models.component import Component


class Gradeable(object):
    """
    Attributes:
        config_path
        id
        type
    """

    def __init__(self, gradeable) -> None:
        self.id = ""
        self.gradeable_config = None
        self.config_path = None
        self.sample_path = None
        self.lichen_sample_path = None
        self.plagiarized_user = {}
        self.title = ""
        self.instructions_url = ""
        self.overall_ta_instructions = ""
        self.peer_grading = False
        self.grade_by_registration = True
        self.grader_assignment_method = 1
        self.is_repository = False
        self.subdirectory = ""
        self.using_subdirectory = False
        self.vcs_partial_path = ""
        self.use_ta_grading = True
        self.late_days = 2
        self.precision = 0.5
        self.syllabus_bucket = "none (for practice only)"
        self.min_grading_group = 3
        self.grading_rotating = []
        self.submissions = []
        self.depends_on = None
        self.depends_on_points = None
        self.max_random_submissions = None
        self.max_individual_submissions = 3
        self.team_assignment = False
        self.max_team_size = 1
        self.has_due_date = True
        self.has_release_date = True
        self.allow_custom_marks = True
        self.plagiarism_submissions = []
        self.plagiarism_versions_per_user = 1
        self.annotated_pdf = False
        self.annotation_path = None
        self.annotations = []

        if "gradeable_config" in gradeable:
            self.gradeable_config = gradeable["gradeable_config"]
            self.type = 0

            if "g_id" in gradeable:
                self.id = gradeable["g_id"]
            else:
                self.id = gradeable["gradeable_config"]

            if "eg_max_random_submissions" in gradeable:
                self.max_random_submissions = int(
                    gradeable["eg_max_random_submissions"]
                )

            if "eg_max_individual_submissions" in gradeable:
                self.max_individual_submissions = int(
                    gradeable["eg_max_individual_submissions"]
                )

            if "config_path" in gradeable:
                self.config_path = gradeable["config_path"]
            else:
                examples_path = os.path.join(
                    MORE_EXAMPLES_DIR, self.gradeable_config, "config"
                )
                tutorial_path = os.path.join(
                    TUTORIAL_DIR, self.gradeable_config, "config"
                )
                if os.path.isdir(examples_path):
                    self.config_path = examples_path
                elif os.path.isdir(tutorial_path):
                    self.config_path = tutorial_path
                else:
                    self.config_path = None

            examples_path = os.path.join(
                MORE_EXAMPLES_DIR, self.gradeable_config, "submissions"
            )
            tutorial_path = os.path.join(
                TUTORIAL_DIR, self.gradeable_config, "submissions"
            )
            if "eg_lichen_sample_path" in gradeable:
                # pdb.set_trace()
                self.lichen_sample_path = gradeable["eg_lichen_sample_path"]
                if "eg_plagiarized_users" in gradeable:
                    for user in gradeable["eg_plagiarized_users"]:
                        temp = user.split(" - ")
                        self.plagiarized_user[temp[0]] = temp[1:]
                else:  # if we weren't given a list of plagiarized users, make one
                    self.plagiarism_submissions = os.listdir(self.lichen_sample_path)
                    random.shuffle(self.plagiarism_submissions)

                if "eg_plagiarism_versions_per_user" in gradeable:
                    self.plagiarism_versions_per_user = gradeable[
                        "plagiarism_versions_per_user"
                    ]

            if "sample_path" in gradeable:
                self.sample_path = gradeable["sample_path"]
            else:
                if os.path.isdir(examples_path):
                    self.sample_path = examples_path
                elif os.path.isdir(tutorial_path):
                    self.sample_path = tutorial_path
                else:
                    self.sample_path = None
        else:
            self.id = gradeable["g_id"]
            self.type = int(gradeable["g_type"])
            self.config_path = gradeable.get("eg_config_path", None)
            self.sample_path = None

        # To make Rainbow Grades testing possible, need to seed random
        m = hashlib.md5()
        m.update(bytes(self.id, "utf-8"))
        random.seed(int(m.hexdigest(), 16))

        if "g_bucket" in gradeable:
            self.syllabus_bucket = gradeable["g_bucket"]

        assert 0 <= self.type <= 2

        if "g_title" in gradeable:
            self.title = gradeable["g_title"]
        else:
            self.title = self.id.replace("_", " ").title()

        if "g_grader_assignment_method" in gradeable:
            self.grade_by_registration = gradeable["g_grader_assignment_method"] == 1
            self.grader_assignment_method = gradeable["g_grader_assignment_method"]

        if "grading_rotating" in gradeable:
            self.grading_rotating = gradeable["grading_rotating"]

        # these dates corresponds with the manually set dates
        time_start = "1900-01-01"
        time_end = "9998-01-01"
        self.ta_view_date = dateutils.parse_datetime(
            gradeable["g_ta_view_start_date"] if "g_ta_view_start_date" in gradeable else time_start
        )
        self.grade_start_date = dateutils.parse_datetime(
            gradeable["g_grade_start_date"] if "g_grade_start_date" in gradeable else time_end
        )
        self.grade_due_date = dateutils.parse_datetime(
            gradeable["g_grade_due_date"] if "g_grade_due_date" in gradeable else time_end
        )
        self.grade_released_date = dateutils.parse_datetime(
            gradeable["g_grade_released_date"] if "g_grade_released_date" in gradeable else time_end
        )
        if self.type == 0:
            self.submission_open_date = dateutils.parse_datetime(
                gradeable["eg_submission_open_date"] if "eg_submission_open_date" in gradeable else time_start
            )
            self.submission_due_date = dateutils.parse_datetime(
                gradeable["eg_submission_due_date"] if "eg_submission_due_date" in gradeable else time_end
            )
            self.team_lock_date = dateutils.parse_datetime(
                gradeable["eg_team_lock_date"] if "eg_team_lock_date" in gradeable else self.submission_due_date
            )
            self.grade_inquiry_start_date = dateutils.parse_datetime(
                gradeable["eg_grade_inquiry_start_date"] if "eg_grade_inquiry_start_date" in gradeable else time_end
            )
            self.grade_inquiry_due_date = dateutils.parse_datetime(
                gradeable["eg_grade_inquiry_due_date"] if "eg_grade_inquiry_due_date" in gradeable else time_end
            )
            self.student_view = True
            self.student_view_after_grades = False
            self.student_download = True
            self.student_submit = True
            if "eg_is_repository" in gradeable:
                self.is_repository = gradeable["eg_is_repository"] is True
            if self.is_repository and "eg_vcs_subdirectory" in gradeable:
                self.using_subdirectory = gradeable["eg_using_subdirectory"] is True
                self.subdirectory = gradeable["eg_vcs_subdirectory"]
                self.vcs_partial_path = gradeable["eg_vcs_partial_path"]
            if "eg_peer_grading" in gradeable:
                self.peer_grading = gradeable["eg_peer_grading"]
            if "eg_use_ta_grading" in gradeable:
                self.use_ta_grading = gradeable["eg_use_ta_grading"] is True
            if "eg_student_view" in gradeable:
                self.student_view = gradeable["eg_student_view"] is True
            if "eg_student_download" in gradeable:
                self.student_download = gradeable["eg_student_download"] is True
            if "eg_student_submit" in gradeable:
                self.student_submit = gradeable["eg_student_submit"] is True
            if "eg_late_days" in gradeable:
                self.late_days = max(0, int(gradeable["eg_late_days"]))
            else:
                self.late_days = random.choice(range(0, 3))
            if "eg_precision" in gradeable:
                self.precision = float(gradeable["eg_precision"])
            if "eg_team_assignment" in gradeable:
                self.team_assignment = gradeable["eg_team_assignment"] is True
            if "eg_max_team_size" in gradeable:
                self.max_team_size = gradeable["eg_max_team_size"]
            if "eg_depends_on" in gradeable:
                self.depends_on = gradeable["eg_depends_on"]
            if "eg_depends_on_points" in gradeable:
                self.depends_on_points = gradeable["eg_depends_on_points"]
            if "eg_annotated_pdf" in gradeable:
                self.annotated_pdf = gradeable["eg_annotated_pdf"] is True
                self.annotation_path = os.path.join(
                    MORE_EXAMPLES_DIR, self.gradeable_config, "annotation"
                )
            if "eg_bulk_test" in gradeable:
                self.student_view = gradeable["eg_bulk_test"] is True
                self.student_view_after_grades = gradeable["eg_bulk_test"] is True

            if "eg_has_due_date" in gradeable:
                self.has_due_date = gradeable["eg_has_due_date"]
            else:
                self.has_due_date = True

            if "eg_has_release_date" in gradeable:
                self.has_release_date = gradeable["eg_has_release_date"]
            else:
                self.has_release_date = True

            if self.config_path is None:
                examples_path = os.path.join(MORE_EXAMPLES_DIR, self.id, "config")
                tutorial_path = os.path.join(TUTORIAL_DIR, self.id, "config")
                if os.path.isdir(examples_path):
                    self.config_path = examples_path
                elif os.path.isdir(tutorial_path):
                    self.config_path = tutorial_path
                else:
                    self.config_path = None
            assert self.ta_view_date <= self.submission_open_date
            assert self.has_due_date is False or (
                self.submission_open_date <= self.submission_due_date
            )
            assert self.has_due_date is False or (
                self.submission_due_date <= self.grade_start_date
            )
            assert self.has_release_date is False or (
                self.grade_released_date <= self.grade_inquiry_start_date
            )
            assert self.grade_inquiry_start_date <= self.grade_inquiry_due_date
            if self.gradeable_config is not None:
                if self.sample_path is not None:
                    if os.path.isfile(
                        os.path.join(self.sample_path, "submissions.yml")
                    ):
                        self.submissions = load_data_yaml(
                            os.path.join(self.sample_path, "submissions.yml")
                        )
                    else:
                        self.submissions = os.listdir(self.sample_path)
                        self.submissions = list(
                            filter(lambda x: not x.startswith("."), self.submissions)
                        )
                        # Ensure we're not sensitive to directory traversal order
                        self.submissions.sort()
                    if isinstance(self.submissions, list):
                        for elem in self.submissions:
                            if isinstance(elem, dict):
                                raise TypeError(
                                    "Cannot have dictionary inside of list for "
                                    f"submissions for {self.sample_path}"
                                )
                if self.annotation_path is not None:
                    self.annotations = os.listdir(self.annotation_path)
                    self.annotations = list(
                        filter(lambda x: not x.startswith("."), self.annotations)
                    )
                    # Ensure we're not sensitive to directory traversal order
                    self.annotations.sort()
        assert self.ta_view_date <= self.grade_start_date
        assert self.grade_start_date <= self.grade_due_date
        assert (
            self.has_release_date is False
            or self.grade_due_date <= self.grade_released_date
        )

        self.components = []
        for i in range(len(gradeable["components"])):
            component = gradeable["components"][i]
            if self.type >= 0:
                component["gc_ta_comment"] = generate_random_ta_note()
                component["gc_student_comment"] = generate_random_student_note()
                component["gc_page"] = 0
            if self.type == 1:
                component["gc_lower_clamp"] = 0
                component["gc_default"] = 0
                component["gc_max_value"] = 1
                component["gc_upper_clamp"] = 1
            if self.type != 2:
                component["gc_is_text"] = False
            i -= 1
            self.components.append(Component(component, i + 1))

    def create(
        self,
        conn,
        gradeable_table,
        electronic_table,
        peer_assign,
        reg_table,
        component_table,
        mark_table,
    ) -> None:
        conn.execute(
            insert(gradeable_table).values(
                g_id=self.id,
                g_title=self.title,
                g_instructions_url=self.instructions_url,
                g_overall_ta_instructions=self.overall_ta_instructions,
                g_gradeable_type=self.type,
                g_grader_assignment_method=self.grader_assignment_method,
                g_ta_view_start_date=self.ta_view_date,
                g_grade_start_date=self.grade_start_date,
                g_grade_due_date=self.grade_due_date,
                g_grade_released_date=self.grade_released_date,
                g_syllabus_bucket=self.syllabus_bucket,
                g_allow_custom_marks=self.allow_custom_marks,
                g_min_grading_group=self.min_grading_group,
            )
        )
        conn.commit()

        for rotate in self.grading_rotating:
            conn.execute(
                insert(reg_table).values(
                    g_id=self.id,
                    user_id=rotate["user_id"],
                    sections_rotating=rotate["section_rotating_id"],
                )
            )
        conn.commit()

        if self.peer_grading is True:
            with open(
                os.path.join(SETUP_DATA_PATH, "random", "graders.txt")
            ) as graders, open(
                os.path.join(SETUP_DATA_PATH, "random", "students.txt")
            ) as students:
                graders = graders.read().strip().split()
                students = students.read().strip().split()
                length = len(graders)
                for i in range(length):
                    conn.execute(
                        insert(peer_assign).values(
                            g_id=self.id,
                            grader_id=graders[i],
                            user_id=students[i],
                        )
                    )
                conn.commit()
        if self.type == 0:
            conn.execute(
                insert(electronic_table).values(
                    g_id=self.id,
                    eg_submission_open_date=self.submission_open_date,
                    eg_submission_due_date=self.submission_due_date,
                    eg_is_repository=self.is_repository,
                    eg_using_subdirectory=self.using_subdirectory,
                    eg_vcs_subdirectory=self.subdirectory,
                    eg_vcs_partial_path=self.vcs_partial_path,
                    eg_team_assignment=self.team_assignment,
                    eg_max_team_size=self.max_team_size,
                    eg_team_lock_date=self.team_lock_date,
                    eg_use_ta_grading=self.use_ta_grading,
                    eg_student_view=self.student_view,
                    eg_student_view_after_grades=self.student_view_after_grades,
                    eg_student_download=self.student_download,
                    eg_student_submit=self.student_submit,
                    eg_config_path=self.config_path,
                    eg_late_days=self.late_days,
                    eg_precision=self.precision,
                    eg_grade_inquiry_start_date=self.grade_inquiry_start_date,
                    eg_grade_inquiry_due_date=self.grade_inquiry_due_date,
                    eg_depends_on=self.depends_on,
                    eg_depends_on_points=self.depends_on_points
                )
            )
            conn.commit()

        for component in self.components:
            component.create(self.id, conn, component_table, mark_table)

    def create_form(self):
        form_json = OrderedDict()
        form_json["gradeable_id"] = self.id
        if self.type == 0:
            form_json["config_path"] = self.config_path
        if self.is_repository:
            form_json["date_due"] = dateutils.write_submitty_date(
                self.submission_due_date
            )
            form_json["upload_type"] = "repository"
            form_json["vcs_partial_path"] = self.vcs_partial_path
            form_json["using_subdirectory"] = self.using_subdirectory
            form_json["subdirectory"] = self.subdirectory
            return form_json
        form_json["gradeable_title"] = self.title
        form_json["gradeable_type"] = self.get_gradeable_type_text()
        form_json["instructions_url"] = self.instructions_url
        form_json["ta_view_date"] = dateutils.write_submitty_date(self.ta_view_date)
        if self.type == 0:
            form_json["date_submit"] = dateutils.write_submitty_date(
                self.submission_open_date
            )
            form_json["date_due"] = dateutils.write_submitty_date(
                self.submission_due_date
            )
            form_json["grade_inquiry_start_date"] = dateutils.write_submitty_date(
                self.grade_inquiry_start_date
            )
            form_json["grade_inquiry_due_date"] = dateutils.write_submitty_date(
                self.grade_inquiry_due_date
            )
        form_json["date_grade"] = dateutils.write_submitty_date(self.grade_start_date)
        form_json["date_grade_due"] = dateutils.write_submitty_date(self.grade_due_date)
        form_json["date_released"] = dateutils.write_submitty_date(
            self.grade_released_date
        )

        if self.type == 0:
            form_json["section_type"] = self.get_submission_type()
            form_json["eg_late_days"] = self.late_days
            form_json["upload_type"] = self.get_upload_type()
            form_json["upload_repo"] = ""
            form_json["comment_title"] = []
            form_json["points"] = []
            form_json["eg_extra"] = []
            form_json["ta_comment"] = []
            form_json["student_comment"] = []
            for i in range(len(self.components)):
                component = self.components[i]
                form_json["comment_title"].append(component.title)
                # form_json['lower_clamp'].append(component.lower_clamp)
                # form_json['default'].append(component.default)
                form_json["points"].append(component.max_value)
                # form_json['upper_clamp'].append(component.upper_clamp)
                form_json["ta_comment"].append(component.ta_comment)
                form_json["student_comment"].append(component.student_comment)
        elif self.type == 1:
            form_json["checkpoint_label"] = []
            form_json["checkpoint_extra"] = []
            form_json["num_text_items"] = 0
            form_json["text_label"] = []
            form_json["num_checkpoint_items"] = 0
            for component in self.components:
                if component.is_text:
                    form_json["num_text_items"] += 1
                    form_json["text_label"].append(component.title)
                else:
                    form_json["checkpoint_label"].append(component.title)
        else:
            form_json["num_numeric_items"] = 0
            form_json["numeric_labels"] = []
            form_json["lower_clamp"] = []
            form_json["default"] = []
            form_json["max_score"] = []
            form_json["upper_clamp"] = []
            form_json["numeric_extra"] = []
            form_json["num_text_items"] = 0
            form_json["text_label"] = []
            for i in range(len(self.components)):
                component = self.components[i]
                if component.is_text:
                    form_json["num_text_items"] += 1
                    form_json["text_label"].append(component.title)
                else:
                    form_json["num_numeric_items"] += 1
                    form_json["numeric_labels"].append(component.title)
                    form_json["lower_clamp"].append(component.lower_clamp)
                    form_json["default"].append(component.default)
                    form_json["max_score"].append(component.max_value)
                    form_json["upper_clamp"].append(component.upper_clamp)
        form_json["minimum_grading_group"] = self.min_grading_group
        form_json["gradeable_buckets"] = self.syllabus_bucket

        return form_json

    def get_gradeable_type_text(self) -> str:
        if self.type == 0:
            return "Electronic File"
        elif self.type == 1:
            return "Checkpoints"
        else:
            return "Numeric"

    def get_submission_type(self) -> str:
        if self.grade_by_registration:
            return "reg_section"
        else:
            return "rotating-section"

    def get_upload_type(self) -> str:
        if self.is_repository:
            return "Repository"
        else:
            return "Upload File"
