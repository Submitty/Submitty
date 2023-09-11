# flake8: noqa
from __future__ import print_function, division
from datetime import timedelta
import hashlib
import json
import os
import random
import shutil
import subprocess
import os.path
import docker
import random
from tempfile import TemporaryDirectory
from submitty_utils import dateutils

from sqlalchemy import create_engine, Table, MetaData, bindparam, select

from sample_courses import *
from sample_courses.utils import (
    get_current_semester,
    mimic_checkout
)
from sample_courses.utils.dependent import add_to_group, commit_submission_to_repo
from sample_courses.utils.create_or_generate import (
    generate_probability_space,
    generate_random_ta_comment,
    generate_versions_to_submit,
    generate_random_user_id,
    create_group,
    create_gradeable_submission,
    create_pdf_annotations
    )
from sample_courses.models.gradeable import Gradeable
from sample_courses.models.course.course_generate_utils import Course_generate_utils
from sample_courses.models.course.course_utils import Course_utils
from sample_courses.models.course.course_data import Course_data

class Course(Course_generate_utils, Course_utils, Course_data):
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
        Course_utils.__init__(self)
        Course_data.__init__(self)
        Course_generate_utils.__init__(self)

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
        gradeable_table = Table("gradeable", self.metadata, autoload=True)
        electronic_table = Table("electronic_gradeable", self.metadata, autoload=True)
        peer_assign = Table("peer_assign", self.metadata, autoload=True)
        reg_table = Table("grading_rotating", self.metadata, autoload=True)
        component_table = Table('gradeable_component', self.metadata, autoload=True)
        mark_table = Table('gradeable_component_mark', self.metadata, autoload=True)
        gradeable_data = Table("gradeable_data", self.metadata, autoload=True)
        gradeable_component_data = Table("gradeable_component_data", self.metadata, autoload=True)
        gradeable_component_mark_data = Table('gradeable_component_mark_data', self.metadata, autoload=True)
        gradeable_data_overall_comment = Table('gradeable_data_overall_comment', self.metadata, autoload=True)
        electronic_gradeable_data = Table("electronic_gradeable_data", self.metadata, autoload=True)
        electronic_gradeable_version = Table("electronic_gradeable_version", self.metadata, autoload=True)
        for gradeable in self.gradeables:
            gradeable.create(self.conn, gradeable_table, electronic_table, peer_assign, reg_table, component_table, mark_table)
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

        anon_ids = {}
        for gradeable in self.gradeables:
            #create gradeable specific anonymous ids for users
            prev_state = random.getstate()
            for user in self.users:
                anon_id = generate_random_user_id(15)
                while anon_id in anon_ids.values():
                    anon_id = generate_random_user_id(15)
                anon_ids[user.id] = anon_id
                gradeable_anon = Table("gradeable_anon", self.metadata, autoload=True)
                self.conn.execute(gradeable_anon.insert(),
                                  user_id=user.id,
                                  g_id=gradeable.id,
                                  anon_id=anon_id)
            random.setstate(prev_state)
            # create_teams
            if gradeable.team_assignment is True:
                json_team_history = self.make_sample_teams(gradeable)
            if gradeable.type == 0 and \
                (len(gradeable.submissions) == 0 or
                 gradeable.sample_path is None or
                 gradeable.config_path is None):
                #  Make sure the electronic gradeable is valid
                continue

            # creating the folder containing all the submissions
            gradeable_path = os.path.join(self.course_path, "submissions", gradeable.id)

            checkout_path = os.path.join(self.course_path, "checkout", gradeable.id)

            if gradeable.is_repository:
                # generate the repos for the vcs gradeable
                print(f"generating repositories for gradeable {gradeable.id}")
                subprocess.check_call(f"sudo {SUBMITTY_INSTALL_DIR}/bin/generate_repos.py {self.semester} {self.code} {gradeable.id}", stdout=subprocess.DEVNULL, stderr=subprocess.STDOUT, shell=True)

            gradeable_annotation_path = os.path.join(self.course_path, "annotations", gradeable.id)

            submission_count = 0
            max_submissions = gradeable.max_random_submissions
            max_individual_submissions = gradeable.max_individual_submissions
            # makes a section be ungraded if the gradeable is not electronic
            ungraded_section = random.randint(1, max(1, self.registration_sections if gradeable.grade_by_registration else self.rotating_sections))
            # This for loop adds submissions/annotations for users and teams(if applicable)
            if not NO_SUBMISSIONS:
                only_submit_plagiarized_users = gradeable.lichen_sample_path is not None and len(gradeable.plagiarized_user) > 0
                for user in self.users:
                    if only_submit_plagiarized_users and user.id not in gradeable.plagiarized_user:
                        continue

                    submitted = False
                    team_id = None
                    anon_team_id = None
                    if gradeable.team_assignment is True:
                        # If gradeable is team assignment, then make sure to make a team_id and don't over submit
                        res = self.conn.execute("SELECT teams.team_id, gradeable_teams.anon_id FROM teams INNER JOIN gradeable_teams\
                        ON teams.team_id = gradeable_teams.team_id where user_id='{}' and g_id='{}'".format(user.id, gradeable.id))
                        temp = res.fetchall()
                        if len(temp) != 0:
                            team_id = temp[0][0]
                            anon_team_id = temp[0][1]
                            previous_submission = select([electronic_gradeable_version]).where(
                                electronic_gradeable_version.c['team_id'] == team_id)
                            res = self.conn.execute(previous_submission)
                            if res.rowcount > 0:
                                continue
                            submission_path = os.path.join(gradeable_path, team_id)
                            annotation_path = os.path.join(gradeable_annotation_path, team_id)
                        else:
                            continue
                        res.close()
                    else:
                        submission_path = os.path.join(gradeable_path, user.id)
                        annotation_path = os.path.join(gradeable_annotation_path, user.id)

                    # need to create the directories for the user/version in "checkout" too for git sunmissions
                    if gradeable.is_repository:
                        user_checkout_path = os.path.join(checkout_path, user.id)
                    else:
                        user_checkout_path = None

                    if gradeable.type == 0 and gradeable.submission_open_date < NOW:
                        if user.id in gradeable.plagiarized_user:
                            # If the user is a bad and unethical student(plagiarized_user), then the version to submit is going to
                            # be the same as the number of assignments defined in users.yml in the lichen_submissions folder.
                            versions_to_submit = len(gradeable.plagiarized_user[user.id])
                        elif gradeable.lichen_sample_path is not None:
                            # if we have set a plagiarism configuration but no manually-specified submissions, submit the default number
                            versions_to_submit = gradeable.plagiarism_versions_per_user
                        else:
                            versions_to_submit = generate_versions_to_submit(max_individual_submissions, max_individual_submissions)

                        if ((gradeable.gradeable_config is not None
                           and (gradeable.has_due_date is True and (gradeable.submission_due_date < NOW or random.random() < 0.5))
                           and (random.random() < 0.9) and (max_submissions is None or submission_count < max_submissions))
                           or (gradeable.gradeable_config is not None and user.id in gradeable.plagiarized_user)):
                            # only create these directories if we're actually going to put something in them
                            if not os.path.exists(gradeable_path):
                                os.makedirs(gradeable_path)
                                os.system("chown -R submitty_php:{}_tas_www {}".format(self.code, gradeable_path))
                            if not os.path.exists(submission_path):
                                os.makedirs(submission_path)
                            if gradeable.is_repository:
                                if not os.path.exists(checkout_path):
                                    os.makedirs(checkout_path)
                                    os.system(f'chown submitty_daemon:{self.code}_tas_www "{checkout_path}"')
                                if not os.path.exists(user_checkout_path):
                                    os.makedirs(user_checkout_path)
                                    os.system(f'chown submitty_daemon:{self.code}_tas_www "{user_checkout_path}"')

                            if gradeable.annotated_pdf is True:
                                if not os.path.exists(gradeable_annotation_path):
                                    os.makedirs(gradeable_annotation_path)
                                if not os.path.exists(annotation_path):
                                    os.makedirs(annotation_path)

                            # Reduce the probability to get a cancelled submission (active_version = 0)
                            # This is done by making other possibilities three times more likely
                            version_population = []
                            for version in range(1, versions_to_submit+1):
                                version_population.append((version, 3))

                            # disallow cancelled submission if this is a manually-specified user
                            if user.id not in gradeable.plagiarized_user:
                                version_population = [(0, 1)] + version_population
                            version_population = [ver for ver, freq in version_population for i in range(freq)]

                            active_version = random.choice(version_population)
                            if team_id is not None:
                                json_history = {"active_version": active_version, "history": [], "team_history": []}
                            else:
                                json_history = {"active_version": active_version, "history": []}
                            random_days = 1
                            if random.random() < 0.3:
                                random_days = random.choice(range(-3, 2))
                            for version in range(1, versions_to_submit+1):
                                os.system("mkdir -p " + os.path.join(submission_path, str(version)))
                                submitted = True
                                submission_count += 1
                                current_time_string = dateutils.write_submitty_date(gradeable.submission_due_date - timedelta(days=random_days+version/versions_to_submit))
                                if team_id is not None:
                                    self.conn.execute(electronic_gradeable_data.insert(), g_id=gradeable.id, user_id=None,
                                                 team_id=team_id, g_version=version, submission_time=current_time_string)
                                    if version == versions_to_submit:
                                        self.conn.execute(electronic_gradeable_version.insert(), g_id=gradeable.id, user_id=None,
                                                     team_id=team_id, active_version=active_version)
                                    json_history["team_history"] = json_team_history[team_id]
                                else:
                                    self.conn.execute(electronic_gradeable_data.insert(), g_id=gradeable.id, user_id=user.id,
                                                g_version=version, submission_time=current_time_string)
                                    if version == versions_to_submit:
                                        self.conn.execute(electronic_gradeable_version.insert(), g_id=gradeable.id, user_id=user.id,
                                                    active_version=active_version)
                                json_history["history"].append({"version": version, "time": current_time_string, "who": user.id, "type": "upload"})

                                with open(os.path.join(submission_path, str(version), ".submit.timestamp"), "w") as open_file:
                                    open_file.write(current_time_string + "\n")

                                if user.id in gradeable.plagiarized_user:
                                    # If the user is in the plagirized folder, then only add those submissions
                                    src = os.path.join(gradeable.lichen_sample_path, gradeable.plagiarized_user[user.id][version-1])
                                    dst = os.path.join(submission_path, str(version))
                                    # pdb.set_trace()
                                    create_gradeable_submission(src, dst)
                                elif gradeable.lichen_sample_path is not None:
                                    if len(gradeable.plagiarism_submissions) > 0:  # check to make sure we haven't run out of data
                                        # if there were no specified plagiarized users but we have plagiarism submissions, grab a random submisison
                                        src = os.path.join(gradeable.lichen_sample_path, gradeable.plagiarism_submissions.pop())
                                        dst = os.path.join(submission_path, str(version))
                                        create_gradeable_submission(src, dst)
                                elif gradeable.annotated_pdf is True:
                                    # Get a list of graders that have access to the submission
                                    assigned_graders = []
                                    stmt = select([
                                        peer_assign.columns.user_id,
                                        peer_assign.columns.grader_id
                                    ]).where(
                                        peer_assign.columns.user_id == user.id
                                    )
                                    for res in self.conn.execute(stmt):
                                        assigned_graders.append(res[1])

                                    submissions = random.sample(gradeable.submissions, random.randint(1, len(gradeable.submissions)))
                                    for submission in submissions:
                                        src = os.path.join(gradeable.sample_path, submission)
                                        dst = os.path.join(submission_path, str(version))
                                        create_gradeable_submission(src, dst)

                                        if version == versions_to_submit:
                                            annotation_version_path = os.path.join(annotation_path, str(versions_to_submit))
                                            if not os.path.exists(annotation_version_path):
                                                os.makedirs(annotation_version_path)

                                            annotations = random.sample(gradeable.annotations, random.randint(1, len(gradeable.annotations)))
                                            graders = random.sample(assigned_graders, len(annotations)-1) if len(assigned_graders) > 0 else []
                                            # Make sure instructor is responsible for one of the annotations
                                            graders.append("instructor")

                                            anon_dst = os.path.join(dst, submission).split("/")
                                            anon_dst[9] = anon_team_id if team_id is not None else anon_ids[user.id]
                                            anon_dst = "/".join(anon_dst) # has the user id or the team id in the file path being anonymous

                                            for i in range(len(graders)):
                                                annotation_src = os.path.join(gradeable.annotation_path, annotations[i])
                                                annotation_dst = os.path.join(annotation_path, str(version))
                                                encoded_path = hashlib.md5(anon_dst.encode()).hexdigest()
                                                # the file name has the format of ENCODED-ANON-SUBMISSION-PATH_GRADER.json
                                                annotation_file_name = f"{str(encoded_path)}_{graders[i]}.json"
                                                create_pdf_annotations(annotation_file_name, anon_dst, annotation_src, annotation_dst, graders[i])
                                else:
                                    if isinstance(gradeable.submissions, dict):
                                        for key in sorted(gradeable.submissions.keys()):
                                            os.system("mkdir -p " + os.path.join(submission_path, str(version), key))
                                            submission = random.choice(gradeable.submissions[key])
                                            src = os.path.join(gradeable.sample_path, submission)
                                            # To mimic a 'checkout', the VCS gradeable files are cloned to the 'user_checkout_ folder
                                            # They are also committed to the repository, so clicking regrade works. 
                                            if gradeable.is_repository:
                                                repo_path = f"{self.semester}/{self.code}/{gradeable.id}/{user.id}"
                                                commit_submission_to_repo(user.id, src, repo_path, gradeable.subdirectory)
                                                mimic_checkout(repo_path, os.path.join(user_checkout_path, str(version)), gradeable.subdirectory)
                                            else:
                                                create_gradeable_submission(src, dst)
                                    else:
                                        submission = random.choice(gradeable.submissions)
                                        if isinstance(submission, list):
                                            submissions = submission
                                        else:
                                            submissions = [submission]
                                        for submission in submissions:
                                            src = os.path.join(gradeable.sample_path, submission)
                                            # To mimic a 'checkout', the VCS gradeable files are cloned to the 'user_checkout_ folder
                                            # They are also committed to the repository, so clicking regrade works. 
                                            if gradeable.is_repository:
                                                repo_path = f"{self.semester}/{self.code}/{gradeable.id}/{user.id}"
                                                commit_submission_to_repo(user.id, src, repo_path, gradeable.subdirectory)
                                                mimic_checkout(repo_path, os.path.join(user_checkout_path, str(version)), gradeable.subdirectory)
                                            else:
                                                dst = os.path.join(submission_path, str(version))
                                                create_gradeable_submission(src, dst)
                                random_days -= 0.5
                            # submissions to vcs greadeable also have a ".submit.VCS_CHECKOUT"
                            if gradeable.is_repository:
                                with open(os.path.join(submission_path, str(version), ".submit.VCS_CHECKOUT"), "w") as open_file:
                                    # the file contains info only if the git repos are non-submitty hosted
                                    pass
                                with open(os.path.join(submission_path, str(version), ".submit.timestamp"), "w") as open_file:
                                    open_file.write(dateutils.write_submitty_date(NOW))

                            else:  
                                with open(os.path.join(submission_path, "user_assignment_settings.json"), "w") as open_file:
                                    json.dump(json_history, open_file)

                    if gradeable.grade_start_date < NOW and os.path.exists(os.path.join(submission_path, str(versions_to_submit))):
                        if (gradeable.has_release_date is True and gradeable.grade_released_date < NOW) or (random.random() < 0.5 and (submitted or gradeable.type !=0)):
                            status = 1 if gradeable.type != 0 or submitted else 0
                            print("Inserting {} for {}...".format(gradeable.id, user.id))
                            values = {'g_id': gradeable.id}
                            overall_comment_values = {'g_id' : gradeable.id,  'goc_overall_comment': 'lorem ipsum lodar', 'goc_grader_id' : self.instructor.id}

                            if gradeable.team_assignment is True:
                                values['gd_team_id'] = team_id
                                overall_comment_values['goc_team_id'] = team_id
                            else:
                                values['gd_user_id'] = user.id
                                overall_comment_values['goc_user_id'] = user.id
                            if gradeable.grade_released_date < NOW and random.random() < 0.5:
                                values['gd_user_viewed_date'] = NOW.strftime('%Y-%m-%d %H:%M:%S%z')
                            ins = gradeable_data.insert().values(**values)
                            res = self.conn.execute(ins)
                            gd_id = res.inserted_primary_key[0]
                            if gradeable.type != 0 or gradeable.use_ta_grading:
                                skip_grading = random.random()
                                if skip_grading > 0.3 and random.random() > 0.01:
                                    ins = gradeable_data_overall_comment.insert().values(**overall_comment_values)
                                    res = self.conn.execute(ins)
                                for component in gradeable.components:
                                    if random.random() < 0.01 and skip_grading < 0.3:
                                        # This is used to simulate unfinished grading.
                                        break
                                    if status == 0 or random.random() < 0.4:
                                        score = 0
                                    else:
                                        max_value_score = random.randint(component.lower_clamp * 2, component.max_value * 2) / 2
                                        uppser_clamp_score = random.randint(component.lower_clamp * 2, component.upper_clamp * 2) / 2
                                        score = generate_probability_space({0.7: max_value_score, 0.2: uppser_clamp_score, 0.08: -max_value_score, 0.02: -99999})
                                    grade_time = gradeable.grade_start_date.strftime("%Y-%m-%d %H:%M:%S%z")
                                    self.conn.execute(gradeable_component_data.insert(), gc_id=component.key, gd_id=gd_id,
                                                 gcd_score=score, gcd_component_comment=generate_random_ta_comment(),
                                                 gcd_grader_id=self.instructor.id, gcd_grade_time=grade_time, gcd_graded_version=versions_to_submit)
                                    first = True
                                    first_set = False
                                    for mark in component.marks:
                                        if (random.random() < 0.5 and first_set == False and first == False) or random.random() < 0.2:
                                            self.conn.execute(gradeable_component_mark_data.insert(), gc_id=component.key, gd_id=gd_id, gcm_id=mark.key, gcd_grader_id=self.instructor.id)
                                            if(first):
                                                first_set = True
                                        first = False

                    if gradeable.type == 0 and os.path.isdir(submission_path):
                        os.system("chown -R submitty_php:{}_tas_www {}".format(self.code, submission_path))

                    if gradeable.type == 0 and os.path.isdir(gradeable_annotation_path):
                        os.system("chown -R submitty_php:{}_tas_www {}".format(self.code, gradeable_annotation_path))

                    if (gradeable.type != 0 and gradeable.grade_start_date < NOW and ((gradeable.has_release_date is True and gradeable.grade_released_date < NOW) or random.random() < 0.5) and
                       random.random() < 0.9 and (ungraded_section != (user.get_detail(self.code, 'registration_section') if gradeable.grade_by_registration else user.get_detail(self.code, 'rotating_section')))):
                        res = self.conn.execute(gradeable_data.insert(), g_id=gradeable.id, gd_user_id=user.id)
                        gd_id = res.inserted_primary_key[0]
                        skip_grading = random.random()
                        for component in gradeable.components:
                            if random.random() < 0.01 and skip_grading < 0.3:
                                break
                            if random.random() < 0.1:
                                continue
                            elif gradeable.type == 1:
                                score = generate_probability_space({0.2: 0, 0.1: 0.5}, 1)
                            else:
                                score = random.randint(component.lower_clamp * 2, component.upper_clamp * 2) / 2
                            grade_time = gradeable.grade_start_date.strftime("%Y-%m-%d %H:%M:%S%z")
                            self.conn.execute(gradeable_component_data.insert(), gc_id=component.key, gd_id=gd_id,
                                         gcd_score=score, gcd_component_comment="", gcd_grader_id=self.instructor.id, gcd_grade_time=grade_time, gcd_graded_version=-1)
        # This segment adds the sample data for features in the sample course only
        if self.code == "sample":
            self.add_sample_forum_data()
            print('Added forum data to sample course.')
            self.add_sample_polls_data()
            print('Added polls data to sample course.')
            self.add_sample_queue_data()
            print('Added office hours queue data to sample course.')

        if self.code == 'sample':
            student_image_folder = os.path.join(SUBMITTY_DATA_DIR, 'courses', self.semester, self.code, 'uploads', 'student_images')
            zip_path = os.path.join(SUBMITTY_REPOSITORY, 'sample_files', 'user_photos', 'CSCI-1300-01.zip')
            with TemporaryDirectory() as tmpdir:
                shutil.unpack_archive(zip_path, tmpdir)
                inner_folder = os.path.join(tmpdir, 'CSCI-1300-01')
                for f in os.listdir(inner_folder):
                    shutil.move(os.path.join(inner_folder, f), os.path.join(student_image_folder, f))
            course_materials_source = os.path.join(SUBMITTY_REPOSITORY, 'sample_files', 'course_materials')
            course_materials_folder = os.path.join(SUBMITTY_DATA_DIR, 'courses', self.semester, self.code, 'uploads', 'course_materials')
            course_materials_table = Table("course_materials", self.metadata, autoload=True)
            for dpath, dirs, files in os.walk(course_materials_source):
                inner_dir=os.path.relpath(dpath, course_materials_source)
                if inner_dir!=".":
                    dir_to_make=os.path.join(course_materials_folder, inner_dir)
                    os.mkdir(dir_to_make)
                    subprocess.run(["chown", "submitty_php:submitty_php", dir_to_make])
                    self.conn.execute(course_materials_table.insert(),
                            path=dir_to_make,
                            type=2,
                            release_date='2022-01-01 00:00:00',
                            hidden_from_students=False,
                            priority=0)
                for f in files:
                    tmpfilepath= os.path.join(dpath,f)
                    filepath=os.path.join(course_materials_folder, os.path.relpath(tmpfilepath, course_materials_source))
                    shutil.copy(tmpfilepath, filepath)
                    subprocess.run(["chown", "submitty_php:submitty_php", filepath])
                    self.conn.execute(course_materials_table.insert(),
                                path=filepath,
                                type=0,
                                release_date='2022-01-01 00:00:00',
                                hidden_from_students=False,
                                priority=0)
        self.conn.close()
        submitty_conn.close()
        os.environ['PGPASSWORD'] = ""

        if self.code == 'tutorial':
            client = docker.from_env()
            client.images.pull('submitty/tutorial:tutorial_18')
            client.images.pull('submitty/tutorial:database_client')
