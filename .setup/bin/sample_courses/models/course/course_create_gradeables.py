"""
None of the functions should be imported here directly, but from
the class Course
"""
from __future__ import print_function, division
from datetime import timedelta
import hashlib
import json
import os
import random
import shutil
import subprocess
import os.path
import random
from tempfile import TemporaryDirectory
from submitty_utils import dateutils

from sqlalchemy import Table, insert, select, text, func

from sample_courses import *
from sample_courses.utils import mimic_checkout
from sample_courses.utils.dependent import commit_submission_to_repo
from sample_courses.utils.create_or_generate import (
    generate_probability_space,
    generate_random_ta_comment,
    generate_versions_to_submit,
    generate_random_user_id,
    create_gradeable_submission,
    create_pdf_annotations
    )


class Course_create_gradeables:
    """
    Object that contain functions that adds gradables to the course and database
    Used as an helper to the Course Class
    """
    semester:str
    # code:dict idk type
    # instructor:dict idk type
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


    def add_gradeables(self) -> None:
        anon_ids = {}
        for gradeable in self.gradeables:
            #create gradeable specific anonymous ids for users
            prev_state = random.getstate()
            for user in self.users:
                anon_id = generate_random_user_id(15)
                while anon_id in anon_ids.values():
                    anon_id = generate_random_user_id(15)
                anon_ids[user.id] = anon_id
                gradeable_anon = Table("gradeable_anon", self.metadata, autoload_with=self.engine)
                self.conn.execute(
                    insert(gradeable_anon).values(
                        user_id=user.id,
                        g_id=gradeable.id,
                        anon_id=anon_id
                    )
                )
                self.conn.commit()
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
                        res = self.conn.execute(text("SELECT teams.team_id, gradeable_teams.anon_id FROM teams INNER JOIN gradeable_teams"
                                                f" ON teams.team_id = gradeable_teams.team_id where user_id='{user.id}' and g_id='{gradeable.id}'"))
                        temp = res.all()
                        if len(temp) != 0:
                            team_id = temp[0][0]
                            anon_team_id = temp[0][1]
                            previous_submission = select(func.count()).select_from(self.electronic_gradeable_version).where(
                                self.electronic_gradeable_version.c.team_id == team_id)
                            rows = self.conn.execute(previous_submission).scalar() or 0
                            if rows > 0:
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
                                os.system(f"chown -R submitty_php:{self.code}_tas_www {gradeable_path}")
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
                                g_notification_sent = gradeable.has_release_date and gradeable.grade_released_date <= dateutils.get_current_time()
                                current_time_string = dateutils.write_submitty_date(gradeable.submission_due_date - timedelta(days=random_days+version/versions_to_submit))
                                if team_id is not None:
                                    self.conn.execute(insert(self.electronic_gradeable_data).values(
                                        g_id=gradeable.id, user_id=None, team_id=team_id,
                                        g_version=version, submission_time=current_time_string
                                    ))
                                    self.conn.commit()
                                    if version == versions_to_submit:
                                        self.conn.execute(
                                            insert(self.electronic_gradeable_version).values(
                                                g_id=gradeable.id, user_id=None, team_id=team_id,
                                                active_version=active_version, g_notification_sent=g_notification_sent
                                            )
                                        )
                                        self.conn.commit()
                                    json_history["team_history"] = json_team_history[team_id]
                                else:
                                    self.conn.execute(
                                            insert(self.electronic_gradeable_data).values(
                                                g_id=gradeable.id, user_id=user.id, g_version=version, submission_time=current_time_string
                                            )
                                    )
                                    self.conn.commit()
                                    if version == versions_to_submit:
                                        self.conn.execute(
                                            insert(self.electronic_gradeable_version).values(
                                                g_id=gradeable.id, user_id=user.id, active_version=active_version, g_notification_sent=g_notification_sent
                                            )
                                        )
                                        self.conn.commit()
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
                                    stmt = select(
                                        self.peer_assign.columns.user_id,
                                        self.peer_assign.columns.grader_id
                                    ).where(
                                        self.peer_assign.columns.user_id == user.id
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
                            print(f"Inserting {gradeable.id} for {user.id}...")
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
                            ins = insert(self.gradeable_data).values(**values)
                            res = self.conn.execute(ins)
                            self.conn.commit()
                            gd_id = res.inserted_primary_key[0]
                            if gradeable.type != 0 or gradeable.use_ta_grading:
                                skip_grading = random.random()
                                if skip_grading > 0.3 and random.random() > 0.01:
                                    ins = insert(self.gradeable_data_overall_comment).values(**overall_comment_values)
                                    res = self.conn.execute(ins)
                                    self.conn.commit()
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
                                    self.conn.execute(
                                        insert(self.gradeable_component_data).values(
                                            gc_id=component.key, gd_id=gd_id, gcd_score=score,
                                            gcd_component_comment=generate_random_ta_comment(), gcd_grader_id=self.instructor.id,
                                            gcd_grade_time=grade_time, gcd_graded_version=versions_to_submit
                                        )
                                    )
                                    self.conn.commit()
                                    first = True
                                    first_set = False
                                    for mark in component.marks:
                                        if (random.random() < 0.5 and first_set == False and first == False) or random.random() < 0.2:
                                            self.conn.execute(
                                                insert(self.gradeable_component_mark_data).values(
                                                    gc_id=component.key, gd_id=gd_id,
                                                    gcm_id=mark.key, gcd_grader_id=self.instructor.id
                                                )
                                            )
                                            if(first):
                                                first_set = True
                                        first = False

                    if gradeable.type == 0 and os.path.isdir(submission_path):
                        os.system(f"chown -R submitty_php:{self.code}_tas_www {submission_path}")

                    if gradeable.type == 0 and os.path.isdir(gradeable_annotation_path):
                        os.system(f"chown -R submitty_php:{self.code}_tas_www {gradeable_annotation_path}")

                    if (gradeable.type != 0 and gradeable.grade_start_date < NOW and ((gradeable.has_release_date is True and gradeable.grade_released_date < NOW) or random.random() < 0.5) and
                       random.random() < 0.9 and (ungraded_section != (user.get_detail(self.code, 'registration_section') if gradeable.grade_by_registration else user.get_detail(self.code, 'rotating_section')))):
                        res = self.conn.execute(insert(self.gradeable_data).values({"g_id": gradeable.id, "gd_user_id": user.id }))
                        self.conn.commit()
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
                            self.conn.execute(
                                insert(self.gradeable_component_data).values(
                                    gc_id=component.key, gd_id=gd_id, gcd_score=score, gcd_component_comment="",
                                    gcd_grader_id=self.instructor.id, gcd_grade_time=grade_time, gcd_graded_version=-1
                                )
                            )
                            self.conn.commit()

        # This segment adds the sample data for features in the sample course only
        if self.code == 'sample':
            self.add_sample_forum_data()
            print('Added forum data to sample course.')
            self.add_sample_polls_data()
            print('Added polls data to sample course.')
            self.add_sample_queue_data()
            print('Added office hours queue data to sample course.')
            
            student_image_folder = os.path.join(SUBMITTY_DATA_DIR, 'courses', self.semester, self.code, 'uploads', 'student_images')
            zip_path = os.path.join(SUBMITTY_REPOSITORY, 'sample_files', 'user_photos', 'CSCI-1300-01.zip')
            with TemporaryDirectory() as tmpdir:
                shutil.unpack_archive(zip_path, tmpdir)
                inner_folder = os.path.join(tmpdir, 'CSCI-1300-01')
                for f in os.listdir(inner_folder):
                    shutil.move(os.path.join(inner_folder, f), os.path.join(student_image_folder, f))
            course_materials_source = os.path.join(SUBMITTY_REPOSITORY, 'sample_files', 'course_materials')
            course_materials_folder = os.path.join(SUBMITTY_DATA_DIR, 'courses', self.semester, self.code, 'uploads', 'course_materials')
            course_materials_table = Table("course_materials", self.metadata, autoload_with=self.engine)
            for dpath, dirs, files in os.walk(course_materials_source):
                inner_dir=os.path.relpath(dpath, course_materials_source)
                if inner_dir!=".":
                    dir_to_make=os.path.join(course_materials_folder, inner_dir)
                    os.mkdir(dir_to_make)
                    subprocess.run(["chown", "submitty_php:submitty_php", dir_to_make])
                    self.conn.execute(
                        insert(course_materials_table).values(
                            path=dir_to_make, type=2, release_date='2022-01-01 00:00:00',
                            hidden_from_students=False, priority=0
                        )
                    )
                    self.conn.commit()
                for f in files:
                    tmpfilepath= os.path.join(dpath,f)
                    filepath=os.path.join(course_materials_folder, os.path.relpath(tmpfilepath, course_materials_source))
                    shutil.copy(tmpfilepath, filepath)
                    subprocess.run(["chown", "submitty_php:submitty_php", filepath])
                    self.conn.execute(
                        insert(course_materials_table).values(
                            path=filepath, type=0, release_date='2022-01-01 00:00:00',
                            hidden_from_students=False, priority=0
                        )
                    )
                self.conn.commit()
