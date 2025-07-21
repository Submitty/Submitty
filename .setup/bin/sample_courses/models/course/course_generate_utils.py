"""
None of the functions should be imported here directly, but from
the class Course
"""
import hashlib
import random
import os
import json

from sqlalchemy import Table, insert, select, func

from submitty_utils import dateutils

from sample_courses import SUBMITTY_INSTALL_DIR, SUBMITTY_DATA_DIR, NOW
from sample_courses.utils import get_current_semester
from sample_courses.utils.create_or_generate import generate_random_user_id


class Course_generate_utils:
    """
    Object that contains functions that are used to generate the course
    """

    # global vars that are instantiated in Class course
    # This is only to type define the global vars to make it easier to debug using
    # intellisense
    semester: str
    # code:dict idk type
    # instructor:dict idk type
    gradeables: list
    make_customization: bool
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

    def make_course_json(self) -> None:
        """
        This function generates customization_sample.json in case it has changed from the provided
        version in the test suite within the Submitty repository. Ideally this function will be
        pulled out and made independent, or better yet when the code for the web interface is done,
        that will become the preferred route and this function can be retired.

        Keeping this function after the web interface would mean we have another place where we
        need to update code anytime the expected format of customization.json changes.

        Right now the code uses the Gradeable and Component classes, so to avoid code duplication
        the function lives inside setup_sample_courses.py

        :return:
        """

        course_id = self.code

        # Reseed to minimize the situations under which customization.json changes
        m = hashlib.md5()
        m.update(bytes(course_id, "utf-8"))
        random.seed(int(m.hexdigest(), 16))

        # Would be great if we could install directly to test_suite, but
        # currently the test uses "clean" which will blow away test_suite
        customization_path = os.path.join(SUBMITTY_INSTALL_DIR, ".setup")
        print(f"Generating customization_{course_id}.json")

        gradeables = {}
        gradeables_json_output = {}

        # Create gradeables by syllabus bucket
        for gradeable in self.gradeables:
            if gradeable.syllabus_bucket not in gradeables:
                gradeables[gradeable.syllabus_bucket] = []
            gradeables[gradeable.syllabus_bucket].append(gradeable)

        # Randomly generate the impact of each bucket on the overall grade
        gradeables_percentages = []
        gradeable_percentage_left = 100 - len(gradeables)
        for _ in range(len(gradeables)):
            gradeables_percentages.append(
                random.randint(1, max(1, gradeable_percentage_left)) + 1
            )
            gradeable_percentage_left -= gradeables_percentages[-1] - 1
        if gradeable_percentage_left > 0:
            gradeables_percentages[-1] += gradeable_percentage_left

        # Compute totals and write out each syllabus bucket in the "gradeables"
        # field of customization.json
        bucket_no = 0

        # for bucket,g_list in gradeables.items():
        for bucket in sorted(gradeables.keys()):
            g_list = gradeables[bucket]
            bucket_json = {
                "type": bucket,
                "count": len(g_list),
                "percent": 0.01 * gradeables_percentages[bucket_no],
                "ids": [],
            }

            g_list.sort(key=lambda x: x.id)

            # Manually total up the non-penalty non-extra-credit max scores, and decide
            # which gradeables are 'released'
            for gradeable in g_list:
                use_ta_grading = gradeable.use_ta_grading
                g_type = gradeable.type
                components = gradeable.components
                g_id = gradeable.id
                max_auto = 0
                max_ta = 0
                print_grades = g_type != 0 or (gradeable.submission_open_date < NOW)

                release_grades = (gradeable.has_release_date is True) and (
                    gradeable.grade_released_date < NOW
                )

                gradeable_config_dir = os.path.join(
                    SUBMITTY_DATA_DIR,
                    "courses",
                    get_current_semester(),
                    "sample",
                    "config",
                    "complete_config",
                )

                # For electronic gradeables there is a config file - read through to get the total
                if os.path.isdir(gradeable_config_dir):
                    gradeable_config = os.path.join(
                        gradeable_config_dir, f"complete_config_{g_id}.json"
                    )
                    if os.path.isfile(gradeable_config):
                        try:
                            with open(gradeable_config, "r") as gradeable_config_file:
                                gradeable_json = json.load(gradeable_config_file)

                                # Not every config has AUTO_POINTS, so have to parse through
                                # test cases
                                # Add points to max if not extra credit, and points>0 (not penalty)
                                if "testcases" in gradeable_json:
                                    for test_case in gradeable_json["testcases"]:
                                        if "extra_credit" in test_case:
                                            continue
                                        if (
                                            "points" in test_case
                                            and test_case["points"] > 0
                                        ):
                                            max_auto += test_case["points"]
                        except EnvironmentError:
                            print("Failed to load JSON")

                # For non-electronic gradeables, or electronic gradeables with TA
                # grading, read through components
                if use_ta_grading or g_type != 0:
                    for component in components:
                        if component.max_value > 0:
                            max_ta += component.max_value

                # Add the specific associative array for this gradeable in customization.json
                # to the output string
                max_points = max_auto + max_ta
                if print_grades:
                    bucket_json["ids"].append({"id": g_id, "max": max_points})
                    if not release_grades:
                        bucket_json["ids"][-1]["released"] = False

            # Close the bucket's array in customization.json
            if "gradeables" not in gradeables_json_output:
                gradeables_json_output["gradeables"] = []
            gradeables_json_output["gradeables"].append(bucket_json)
            bucket_no += 1

        # Generate the section labels
        section_ta_mapping = {}
        for section in range(1, self.registration_sections + 1):
            section_ta_mapping[section] = []
        for user in self.users:
            if user.get_detail(course_id, "grading_registration_section") is not None:
                grading_registration_sections = str(
                    user.get_detail(course_id, "grading_registration_section")
                )
                grading_registration_sections = [
                    int(x) for x in grading_registration_sections.split(",")
                ]
                for section in grading_registration_sections:
                    section_ta_mapping[section].append(user.id)

        for section in section_ta_mapping:
            if len(section_ta_mapping[section]) == 0:
                section_ta_mapping[section] = "TBA"
            else:
                section_ta_mapping[section] = ", ".join(section_ta_mapping[section])

        # Construct the rest of the JSON dictionary
        benchmarks = ["a-", "b-", "c-", "d"]
        gradeables_json_output["display"] = [
            "instructor_notes",
            "grade_summary",
            "grade_details",
        ]
        gradeables_json_output["display_benchmark"] = ["average", "stddev", "perfect"]
        gradeables_json_output["benchmark_percent"] = {}
        for i in range(len(benchmarks)):
            gradeables_json_output["display_benchmark"].append(
                "lowest_" + benchmarks[i]
            )
            gradeables_json_output["benchmark_percent"][
                "lowest_" + benchmarks[i]
            ] = 0.9 - (0.1 * i)

        gradeables_json_output["section"] = section_ta_mapping
        messages = [
            f"<b>{course_id} Course</b>",
            "Note: Please be patient with data entry/grade corrections for the most "
            "recent lab, homework, and test.",
            "Please contact your graduate lab TA "
            "if a grade remains missing or incorrect for more than a week.",
        ]
        gradeables_json_output["messages"] = messages

        # Attempt to write the customization.json file
        try:
            build_customization_path = os.path.join(
                customization_path, "customization_" + course_id + ".json"
            )
            with open(build_customization_path, "w") as customization_file:
                customization_file.write(
                    "/*\n"
                    "This JSON is based on the automatically "
                    "generated customization for\n"
                    f'the development course "{course_id}" as of '
                    f"{NOW.strftime('%Y-%m-%d %H:%M:%S%z')}.\n"
                    "It is intended as a simple example, "
                    "with additional documentation online.\n"
                    "*/\n"
                )
            temp_custom_path = os.path.join(
                customization_path, f"customization_{course_id}.json"
            )
            json.dump(gradeables_json_output, open(temp_custom_path, "a"), indent=2)
        except EnvironmentError as e:
            print(f"Failed to write to customization file: {e}")

        print(f"Wrote customization_{course_id}.json")

    def make_sample_teams(self, gradeable):
        """
        arg: any team gradeable

        This function adds teams to the database and gradeable.

        return: A json object filled with team information
        """
        assert gradeable.team_assignment
        json_team_history = {}
        gradeable_teams_table = Table("gradeable_teams", self.metadata, autoload_with=self.conn)
        teams_table = Table("teams", self.metadata, autoload_with=self.conn)
        ucounter = self.conn.execute(
            select(func.count()).select_from(gradeable_teams_table)
        ).scalar()
        anon_team_ids = []
        for user in self.users:
            # the unique team id is made up of 5 digits, an underline, and the
            # team creater's userid.
            # example: 00001_aphacker
            unique_team_id = (
                str(ucounter).zfill(5) + "_" + user.get_detail(self.code, "id")
            )
            # also need to create and save the anonymous team id
            anon_team_id = generate_random_user_id(15)
            if anon_team_id in anon_team_ids:
                anon_team_id = generate_random_user_id()
            reg_section = user.get_detail(self.code, "registration_section")
            if reg_section is None:
                continue
            # The teams are created based on the order of the users.
            # As soon as the number of teamates
            # exceeds the max team size, then a new team will be created within
            # the same registration section
            print("Adding team for " + unique_team_id + " in gradeable " + gradeable.id)
            # adding json data for team history
            teams_registration = select(gradeable_teams_table).where(
                (gradeable_teams_table.c["registration_section"] == str(reg_section))
                & (gradeable_teams_table.c["g_id"] == gradeable.id)
            )
            res = self.conn.execute(teams_registration)
            added = False
            if res.rowcount != 0:
                # If the registration has a team already, join it
                for team_in_section in res.mappings():
                    members_in_team = select(teams_table).where(
                        teams_table.c["team_id"] == team_in_section["team_id"]
                    )
                    res = self.conn.execute(members_in_team)
                    if res.rowcount < gradeable.max_team_size:
                        self.conn.execute(
                            insert(teams_table).values(
                                team_id=team_in_section["team_id"],
                                user_id=user.get_detail(self.code, "id"),
                                state=1,
                            )
                        )
                        self.conn.commit()
                        team_id_section = team_in_section["team_id"]
                        temp_json_team_history = {
                            "action": "admin_create",
                            "time": dateutils.write_submitty_date(
                                gradeable.submission_open_date
                            ),
                            "admin_user": "instructor",
                            "added_user": user.get_detail(self.code, "id"),
                        }
                        json_team_history[team_id_section].append(
                            temp_json_team_history
                        )
                        added = True
            if not added:
                # if the team the user tried to join is full, make a new team
                self.conn.execute(
                    insert(gradeable_teams_table).values(
                        team_id=unique_team_id,
                        anon_id=anon_team_id,
                        g_id=gradeable.id,
                        registration_section=str(reg_section),
                        rotating_section=str(random.randint(1, self.rotating_sections)),
                    )
                )
                self.conn.execute(
                    insert(teams_table).values(
                        team_id=unique_team_id,
                        user_id=user.get_detail(self.code, "id"),
                        state=1,
                    )
                )
                self.conn.commit()
                json_team_history[unique_team_id] = [
                    {
                        "action": "admin_create",
                        "time": dateutils.write_submitty_date(
                            gradeable.submission_open_date
                        ),
                        "admin_user": "instructor",
                        "first_user": user.get_detail(self.code, "id"),
                    }
                ]
                ucounter += 1
            res.close()
            anon_team_ids.append(anon_team_id)
        return json_team_history
