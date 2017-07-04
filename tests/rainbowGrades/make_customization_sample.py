import pprint
from datetime import datetime, timedelta
import yaml
import os
import re
import json

# FIXME: Tons of replicated code from setup_sample_courses.py - should either migrate this script's main to
# setup_sample_courses.py or otherwise reduce overlap?

TUTORIAL_DIR = "__INSTALL__FILLIN__SUBMITTY_TUTORIAL_DIR__"
MORE_EXAMPLES_DIR = os.path.join("__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__","more_autograding_examples")
NOW = datetime.now()

def load_data_yaml(file_path):
    """
    Loads yaml file from the .setup/data directory returning the parsed structure
    :param file_path: name of file to load
    :return: parsed YAML structure from loaded file
    """
    if not os.path.isfile(file_path):
        raise IOError("Missing the yaml file {}".format(file_path))
    with open(file_path) as open_file:
        yaml_file = yaml.safe_load(open_file)
    return yaml_file


class Gradeable(object):
    """
    Attributes:
        config_path
        id
        type
    """
    def __init__(self, gradeable):
        self.id = ""
        self.gradeable_config = None
        self.config_path = None
        self.sample_path = None
        self.title = ""
        self.instructions_url = ""
        self.overall_ta_instructions = ""
        self.team_assignment = False
        self.grade_by_registration = True
        self.is_repository = False
        self.subdirectory = ""
        self.use_ta_grading = True
        self.late_days = 2
        self.precision = 0.5
        self.syllabus_bucket = "none (for practice only)"
        self.min_grading_group = 3
        self.grading_rotating = []
        self.submissions = []
        self.max_random_submissions = None

        if 'gradeable_config' in gradeable:
            self.gradeable_config = gradeable['gradeable_config']
            self.type = 0

            if 'g_id' in gradeable:
                self.id = gradeable['g_id']
            else:
                self.id = gradeable['gradeable_config']

            if 'eg_max_random_submissions' in gradeable:
                self.max_random_submissions = int(gradeable['eg_max_random_submissions'])

            if 'config_path' in gradeable:
                self.config_path = gradeable['config_path']
            else:
                examples_path = os.path.join(MORE_EXAMPLES_DIR, self.gradeable_config, "config")
                tutorial_path = os.path.join(TUTORIAL_DIR, self.gradeable_config, "config")
                if os.path.isdir(examples_path):
                    self.config_path = examples_path
                elif os.path.isdir(tutorial_path):
                    self.config_path = tutorial_path
                else:
                    self.config_path = None

            examples_path = os.path.join(MORE_EXAMPLES_DIR, self.gradeable_config, "submissions")
            tutorial_path = os.path.join(TUTORIAL_DIR, self.gradeable_config, "submissions")
            if 'sample_path' in gradeable:
                self.sample_path = gradeable['sample_path']
            else:
                if os.path.isdir(examples_path):
                    self.sample_path = examples_path
                elif os.path.isdir(tutorial_path):
                    self.sample_path = tutorial_path
                else:
                    self.sample_path = None
        else:
            self.id = gradeable['g_id']
            self.type = int(gradeable['g_type'])
            self.config_path = None
            self.sample_path = None

        assert 0 <= self.type <= 2

        if 'g_bucket' in gradeable:
            self.syllabus_bucket = gradeable['g_bucket']

        if 'g_title' in gradeable:
            self.title = gradeable['g_title']
        else:
            self.title = self.id.replace("_", " ").title()

        if 'g_grade_by_registration' in gradeable:
            self.grade_by_registration = gradeable['g_grade_by_registration'] is True

        if 'grading_rotating' in gradeable:
            self.grading_rotating = gradeable['grading_rotating']

        self.ta_view_date = parse_datetime(gradeable['g_ta_view_start_date'])
        self.grade_start_date = parse_datetime(gradeable['g_grade_start_date'])
        self.grade_released_date = parse_datetime(gradeable['g_grade_released_date'])
        if self.type == 0:
            self.submission_open_date = parse_datetime(gradeable['eg_submission_open_date'])
            self.submission_due_date = parse_datetime(gradeable['eg_submission_due_date'])
            if 'eg_is_repository' in gradeable:
                self.is_repository = gradeable['eg_is_repository'] is True
            if self.is_repository and 'eg_subdirectory' in gradeable:
                self.subdirectory = gradeable['eg_subdirectory']
            if 'eg_use_ta_grading' in gradeable:
                self.use_ta_grading = gradeable['eg_use_ta_grading'] is True
            if 'eg_late_days' in gradeable:
                self.late_days = max(0, int(gradeable['eg_late_days']))
            if 'eg_precision' in gradeable:
                self.precision = float(gradeable['eg_precision'])
            if self.config_path is None:
                examples_path = os.path.join(MORE_EXAMPLES_DIR, self.id, "config")
                tutorial_path = os.path.join(TUTORIAL_DIR, self.id, "config")
                if os.path.isdir(examples_path):
                    self.config_path = examples_path
                elif os.path.isdir(tutorial_path):
                    self.config_path = tutorial_path
                else:
                    self.config_path = None
            assert self.ta_view_date < self.submission_open_date
            assert self.submission_open_date < self.submission_due_date
            assert self.submission_due_date < self.grade_start_date
            if self.gradeable_config is not None:
                if self.sample_path is not None:
                    if os.path.isfile(os.path.join(self.sample_path, "submissions.yml")):
                        self.submissions = load_data_yaml(os.path.join(self.sample_path, "submissions.yml"))
                    else:
                        self.submissions = os.listdir(self.sample_path)
                        self.submissions = list(filter(lambda x: not x.startswith("."), self.submissions))
                    if isinstance(self.submissions, list):
                        for elem in self.submissions:
                            if isinstance(elem, dict):
                                raise TypeError("Cannot have dictionary inside of list for submissions "
                                                "for {}".format(self.sample_path))
        assert self.ta_view_date < self.grade_start_date
        assert self.grade_start_date < self.grade_released_date

        self.components = []
        for i in range(len(gradeable['components'])):
            component = gradeable['components'][i]
            if self.type < 2:
                component['gc_is_text'] = False
            elif self.type > 0:
                component['gc_ta_comment'] = ""
                component['gc_student_comment'] = ""

            if self.type == 1:
                component['gc_max_value'] = 1
            self.components.append(Component(component, i+1))


class Component(object):
    def __init__(self, component, order):
        self.title = component['gc_title']
        self.ta_comment = ""
        self.student_comment = ""

        self.is_text = False
        self.is_extra_credit = False
        self.order = order
        if 'gc_ta_comment' in component:
            self.ta_comment = component['gc_ta_comment']
        if 'gc_student_comment' in component:
            self.student_comment = component['gc_student_comment']
        if 'gc_is_text' in component:
            self.is_text = component['gc_is_text'] is True
        if 'gc_is_extra_credit' in component:
            self.is_extra_credit = component['gc_is_extra_credit'] is True

        if self.is_text:
            self.max_value = 0
        else:
            self.max_value = float(component['gc_max_value'])

        self.key = None

    def create(self, g_id, conn, table):
        ins = table.insert().values(g_id=g_id, gc_title=self.title, gc_ta_comment=self.ta_comment,
                                    gc_student_comment=self.student_comment,
                                    gc_max_value=self.max_value, gc_is_text=self.is_text,
                                    gc_is_extra_credit=self.is_extra_credit, gc_order=self.order)
        res = conn.execute(ins)
        self.key = res.inserted_primary_key[0]

def parse_datetime(date_string):
    """
    Given a string that should either represent an absolute date or an arbitrary date, parse this
    into a datetime object that is then used. Absolute dates should be in the format of
    YYYY-MM-DD HH:MM:SS while arbitrary dates are of the format "+/-# day(s) [at HH:MM:SS]" where
    the last part is optional. If the time is omitted, then it uses midnight of whatever day was
    specified.

    Examples of allowed strings:
    2016-10-14
    2016-10-13 22:11:32
    -1 day
    +2 days at 00:01:01

    :param date_string:
    :type date_string: str
    :return:
    :rtype: datetime
    """
    if isinstance(date_string, datetime):
        return date_string
    try:
        return datetime.strptime(date_string, "%Y-%m-%d %H:%M:%S")
    except ValueError:
        pass

    try:
        return datetime.strptime(date_string, "%Y-%m-%d").replace(hour=23, minute=59, second=59)
    except ValueError:
        pass

    m = re.search('([+|\-][0-9]+) (days|day) at [0-2][0-9]:[0-5][0-9]:[0-5][0-9]', date_string)
    if m is not None:
        hour = int(m.group(2))
        minu = int(m.group(3))
        sec = int(m.group(4))
        days = int(m.group(1))
        return NOW.replace(hour=hour, minute=minu, second=sec) + timedelta(days=days)

    m = re.search('([+|\-][0-9]+) (days|day)', date_string)
    if m is not None:
        days = int(m.group(1))
        return NOW.replace(hour=23, minute=59, second=59) + timedelta(days=days)

    raise ValueError("Invalid string for date parsing: " + str(date_string))


def get_current_semester():
    """
    Given today's date, generates a three character code that represents the semester to use for
    courses such that the first half of the year is considered "Spring" and the last half is
    considered "Fall". The "Spring" semester  gets an S as the first letter while "Fall" gets an
    F. The next two characters are the last two digits in the current year.
    :return:
    """
    today = datetime.today()
    semester = "f" + str(today.year)[-2:]
    if today.month < 7:
        semester = "s" + str(today.year)[-2:]
    return semester


def main():
    customization_path = os.path.join("__INSTALL__FILLIN__SUBMITTY_INSTALL_DIR__", "test_suite", "rainbowGrades")

    course_file = os.path.join("__INSTALL__FILLIN__SUBMITTY_REPOSITORY__", ".setup", "data", "courses", "sample.yml")
    course_json = load_data_yaml(course_file)

    course_id = course_json['code']
    print("Making customization.json for course: {}".format(course_id))

    gradeables = {}
    gradeables_json_output = ""

    for g in course_json['gradeables']:
        gradeable = Gradeable(g)
        if gradeable.syllabus_bucket not in gradeables:
            gradeables[gradeable.syllabus_bucket] = []
        gradeables[gradeable.syllabus_bucket].append(gradeable)

    for bucket,g_list in gradeables.items():
        gradeables_json_output += "    {\n"
        # FIXME: Do something more intelligent for percentages?
        gradeables_json_output += "      \"type\": \"{}\",\n".format(bucket) + \
                                  "      \"count\": {},\n".format(len(g_list)) + \
                                  "      \"percent\" : {},\n".format(1.00) + \
                                  "      \"ids\": [\n"
        for g in g_list:
            use_ta_grading = g.use_ta_grading
            g_type = g.type
            components = g.components
            id = g.id
            max_auto = 0
            max_ta = 0

            print_grades = True if g_type !=0 or (g.submission_open_date < NOW) else False
            release_grades = (g.grade_released_date < NOW)

            gradeable_config_dir = os.path.join("__INSTALL__FILLIN__SUBMITTY_DATA_DIR__", "courses",
                                                get_current_semester(), "sample", "config", "complete_config")

            if os.path.isdir(gradeable_config_dir):
                gradeable_config = os.path.join(gradeable_config_dir,"complete_config_" + id + ".json")
                if os.path.isfile(gradeable_config):
                    try:
                        with open(gradeable_config,'r') as gradeable_config_file:
                            gradeable_json = json.load(gradeable_config_file)

                            # Not every config has AUTO_POINTS, so have to parse through test cases
                            # Add points to max if not extra credit, and points>0 (not penalty)
                            if "testcases" in gradeable_json:
                                for test_case in gradeable_json["testcases"]:
                                    if "extra_credit" in test_case:
                                        continue
                                    if "points" in test_case and test_case["points"] > 0:
                                        max_auto += test_case["points"]
                    except EnvironmentError:
                        print("Failed to load JSON")

            if use_ta_grading or g_type != 0:
                for component in components:
                    if component.is_extra_credit:
                        continue
                    if component.max_value >0:
                        max_ta += component.max_value

            max_points = max_auto+max_ta
            if print_grades:
                gradeables_json_output += "        {{\"id\":\"{}\", \"max\":{}".format(id,max_points)
                if not release_grades:
                    gradeables_json_output += ", \"released\":false"

                if g != g_list[-1]:
                    gradeables_json_output += "},\n"
                else:
                    gradeables_json_output += "}\n"
        gradeables_json_output += "      ]\n" + "    }"
        if bucket != gradeables.keys()[-1]:
            gradeables_json_output += ",\n"
        else:
            gradeables_json_output += "\n"


    try:
        with open(os.path.join(customization_path, "customization_sample.json"), 'w') as customization_file:
            customization_file.write("{\n" +
                                     "  \"display\": [\n" +
                                     "    \"instructor_notes\",\n" +
                                     "    \"grade_summary\",\n" +
                                     "    \"grade_details\"\n" +
                                     "  ],\n" +
                                     "  \"display_benchmark\": [\n" +
                                     "    \"average\",\n" +
                                     "    \"stddev\",\n" +
                                     "    \"perfect\",\n" +
                                     "    \"lowest_a-\",\n" +
                                     "    \"lowest_b-\",\n" +
                                     "    \"lowest_c-\",\n" +
                                     "    \"lowest_d\"\n" +
                                     "  ],\n" +
                                     "  \"benchmark_percent\": {\n" +
                                     "    \"lowest_a-\": 0.9,\n" +
                                     "    \"lowest_b-\": 0.8,\n" +
                                     "    \"lowest_c-\": 0.7,\n" +
                                     "    \"lowest_d\": 0.6\n" +
                                     "  },\n" +
                                     "  \"gradeables\": [\n" +
                                     gradeables_json_output +
                                     "  ],\n" +
                                     "  \"section\": {\n" +
                                     "    \"1\": \"TA_name_1\",\n" +
                                     "    \"2\": \"TA_name_2\",\n" +
                                     "    \"3\": \"TA_name_3\",\n" +
                                     "    \"4\": \"TA_name_4\",\n" +
                                     "    \"5\": \"TA_name_1\",\n" +
                                     "    \"6\": \"TA_name_2\",\n" +
                                     "    \"7\": \"TA_name_3\",\n" +
                                     "    \"8\": \"TA_name_4\",\n" +
                                     "    \"9\": \"TA_name_5\",\n" +
                                     "    \"10\": \"TA_name_5\"\n" +
                                     "  },\n" +
                                     "  \"messages\": [\n" +
                                     "    \"<b>My Favorite CS Class</b>\",\n" +
                                     "    \"Note: Please be patient with data entry/grade corrections for the" +
                                     " most recent lab, homework, and test.\",\n" +
                                     "    \"Please contact your graduate lab TA if a grade remains missing or" +
                                     " incorrect for more than a week.\"\n" +
                                     "  ]\n" +
                                     "}\n")

            # Write out the gradeables
            #TODO: Figure out what to do about "none" bucket items - does Rainbow Grades support this?
    except EnvironmentError as e:
        print("Failed to write to customization file: {}".format(e))


if __name__ == '__main__':
    main()
