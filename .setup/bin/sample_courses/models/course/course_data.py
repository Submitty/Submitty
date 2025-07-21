"""
None of the functions should be imported here directly, but from
the class Course
"""
import os
import json
import random
from shutil import copyfile, rmtree
from datetime import datetime, timedelta

from sqlalchemy import Table, insert

from sample_courses import SETUP_DATA_PATH


class Course_data:
    """
    Contains functions that add/create data to the course
    """

    # global vars that are instantiated in Class course
    # This is only to type define the global vars to make it easier to debug using
    # intellisense
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

    def __init__(self):
        # Anything that needs to be initialized goes here
        pass

    def add_sample_queue_data(self) -> None:
        # load the sample polls from input file
        queue_data_path = os.path.join(SETUP_DATA_PATH, "queue", "queue_data.json")
        with open(queue_data_path, "r") as queue_file:
            queue_data = json.load(queue_file)

        # set sample course to have office hours queue enabled by default
        course_json_file = os.path.join(self.course_path, "config", "config.json")
        with open(course_json_file, "r+") as open_file:
            course_json = json.load(open_file)
            course_json["course_details"]["queue_enabled"] = True
            course_json["course_details"]["queue_message"] = queue_data["queue_message"]
            course_json["course_details"]["queue_announcement_message"] = queue_data[
                "queue_announcement_message"
            ]
            open_file.seek(0)
            open_file.truncate()
            json.dump(course_json, open_file, indent=2)

        # generate values that depend on current date and time
        # helped for the first time today, done --- LAB queue
        queue_data["queue_entries"][0]["time_in"] = datetime.now() - timedelta(
            minutes=25
        )
        queue_data["queue_entries"][0]["time_out"] = datetime.now() - timedelta(
            minutes=19
        )
        queue_data["queue_entries"][0]["time_help_start"] = datetime.now() - timedelta(
            minutes=24
        )
        # helped, done --- LAB queue
        queue_data["queue_entries"][1]["time_in"] = datetime.now() - timedelta(
            minutes=24
        )
        queue_data["queue_entries"][1]["time_out"] = datetime.now() - timedelta(
            minutes=15
        )
        queue_data["queue_entries"][1]["time_help_start"] = datetime.now() - timedelta(
            minutes=23
        )
        # removed by self --- LAB queue
        queue_data["queue_entries"][2]["time_in"] = datetime.now() - timedelta(
            minutes=22
        )
        queue_data["queue_entries"][2]["time_out"] = datetime.now() - timedelta(
            minutes=21
        )
        # being helped --- HW queue
        queue_data["queue_entries"][3]["time_in"] = datetime.now() - timedelta(
            minutes=23
        )
        queue_data["queue_entries"][3]["time_help_start"] = datetime.now() - timedelta(
            minutes=14
        )
        # waiting for help for second time today --- LAB queue
        queue_data["queue_entries"][4]["time_in"] = datetime.now() - timedelta(
            minutes=21
        )
        queue_data["queue_entries"][4]["last_time_in_queue"] = queue_data[
            "queue_entries"
        ][0]["time_in"]
        # paused --- HW queue
        queue_data["queue_entries"][5]["time_in"] = datetime.now() - timedelta(
            minutes=20
        )
        queue_data["queue_entries"][5][
            "time_paused_start"
        ] = datetime.now() - timedelta(minutes=18)
        # wait for the first time --- HW queue
        queue_data["queue_entries"][6]["time_in"] = datetime.now() - timedelta(
            minutes=15
        )
        # waiting for help for second time this week --- LAB queue
        queue_data["queue_entries"][7]["time_in"] = datetime.now() - timedelta(
            minutes=10
        )
        queue_data["queue_entries"][7][
            "last_time_in_queue"
        ] = datetime.now() - timedelta(days=1, minutes=30)

        queues_table = Table("queue_settings", self.metadata, autoload_with=self.conn)
        queue_entries_table = Table("queue", self.metadata, autoload_with=self.conn)

        # make two sample queues
        self.conn.execute(
            insert(queues_table).values(open=True, code="Lab Help", token="lab")
        )
        self.conn.execute(
            insert(queues_table).values(open=True, code="Homework Debugging", token="hw_debug")
        )
        self.conn.commit()

        # add, help, remove, pause, etc. students in the queue
        for queue_entry in queue_data["queue_entries"]:
            self.conn.execute(
                insert(queue_entries_table).values(
                    current_state=queue_entry["current_state"],
                    removal_type=queue_entry["removal_type"],
                    queue_code=queue_entry["queue_code"],
                    user_id=queue_entry["user_id"],
                    name=queue_entry["name"],
                    time_in=queue_entry["time_in"],
                    time_out=queue_entry["time_out"],
                    added_by=queue_entry["added_by"],
                    help_started_by=queue_entry["help_started_by"],
                    removed_by=queue_entry["removed_by"],
                    contact_info=queue_entry["contact_info"],
                    last_time_in_queue=queue_entry["last_time_in_queue"],
                    time_help_start=queue_entry["time_help_start"],
                    paused=queue_entry["paused"],
                    time_paused=queue_entry["time_paused"],
                    time_paused_start=queue_entry["time_paused_start"],
                )
            )
            self.conn.commit()

    def add_sample_polls_data(self) -> None:
        # set sample course to have polls enabled by default
        course_json_file = os.path.join(self.course_path, "config", "config.json")
        with open(course_json_file, "r+") as open_file:
            course_json = json.load(open_file)
            course_json["course_details"]["polls_enabled"] = True
            open_file.seek(0)
            open_file.truncate()
            json.dump(course_json, open_file, indent=2)

        # load the sample polls from input file
        polls_data_path = os.path.join(SETUP_DATA_PATH, "polls", "polls_data.json")
        with open(polls_data_path, "r") as polls_file:
            polls_data = json.load(polls_file)

        # set some values that depend on current time
        polls_data[0]["image_path"] = self.course_path + polls_data[0]["image_path"]
        polls_data[2]["release_date"] = f"{datetime.today().date()}"

        # add attached image
        image_dir = os.path.dirname(polls_data[0]["image_path"])
        if os.path.isdir(image_dir):
            rmtree(image_dir)

        os.makedirs(image_dir)
        os.system(f"chown -R submitty_php:sample_tas_www {image_dir}")
        copyfile(
            os.path.join(SETUP_DATA_PATH, "polls", "sea_animals.png"),
            polls_data[0]["image_path"],
        )

        # add polls to DB
        polls_table = Table("polls", self.metadata, autoload_with=self.conn)
        poll_options_table = Table("poll_options", self.metadata, autoload_with=self.conn)
        poll_responses_table = Table("poll_responses", self.metadata, autoload_with=self.conn)

        for poll in polls_data:
            self.conn.execute(
                insert(polls_table).values(
                    name=poll["name"],
                    question=poll["question"],
                    end_time=poll["end_time"],
                    is_visible=poll["is_visible"],
                    release_date=poll["release_date"],
                    image_path=poll["image_path"],
                    question_type=poll["question_type"],
                    release_histogram=poll["release_histogram"],
                )
            )
            self.conn.commit()
            for i in range(len(poll["responses"])):
                self.conn.execute(
                    insert(poll_options_table).values(
                        order_id=i,
                        poll_id=poll["id"],
                        response=poll["responses"][i],
                        correct=(i in poll["correct_responses"]),
                    )
                )
                self.conn.commit()

        # generate responses to the polls
        poll_responses_data = []
        # poll1: for each self.users make a random number (0-5) of responses
        poll1_response_ids = list(range(len(polls_data[0]["responses"])))
        for user in self.users:
            random_responses = random.sample(
                poll1_response_ids, random.randint(0, len(polls_data[0]["responses"]))
            )
            for response_id in random_responses:
                poll_responses_data.append(
                    {
                        "poll_id": polls_data[0]["id"],
                        "student_id": user.id,
                        "option_id": response_id + 1,
                    }
                )
        # poll2: take a large portion of self.users and make each submit one random response
        for user in self.users:
            if random.random() < 0.8:
                generate_rand_int = random.randint(
                    1, len(polls_data[1]["responses"])
                ) + len(polls_data[0]["responses"])

                poll_responses_data.append(
                    {
                        "poll_id": polls_data[1]["id"],
                        "student_id": user.id,
                        # Must offset by number of options for poll 1
                        "option_id": generate_rand_int,
                    }
                )

        # add responses to DB
        for response in poll_responses_data:
            self.conn.execute(
                insert(poll_responses_table).values(
                    poll_id=response["poll_id"],
                    student_id=response["student_id"],
                    option_id=response["option_id"],
                )
            )
            self.conn.commit()

    def add_sample_forum_data(self) -> None:
        # set sample course to have forum enabled by default
        course_json_file = os.path.join(self.course_path, "config", "config.json")
        with open(course_json_file, "r+") as open_file:
            course_json = json.load(open_file)
            course_json["course_details"]["forum_enabled"] = True
            open_file.seek(0)
            open_file.truncate()
            json.dump(course_json, open_file, indent=2)

        f_data = (
            self.getForumDataFromFile("posts.txt"),
            self.getForumDataFromFile("threads.txt"),
            self.getForumDataFromFile("categories.txt"),
        )
        forum_threads = Table("threads", self.metadata, autoload_with=self.conn)
        forum_posts = Table("posts", self.metadata, autoload_with=self.conn)
        forum_cat_list = Table("categories_list", self.metadata, autoload_with=self.conn)
        forum_thread_cat = Table("thread_categories", self.metadata, autoload_with=self.conn)

        for catData in f_data[2]:
            self.conn.execute(
                insert(forum_cat_list).values(
                    category_desc=catData[0],
                    rank=catData[1],
                    color=catData[2],
                )
            )
        self.conn.commit()

        for thread_id, threadData in enumerate(f_data[1], start=1):
            self.conn.execute(
                insert(forum_threads).values(
                    title=threadData[0],
                    created_by=threadData[1],
                    pinned=True if threadData[2] == "t" else False,
                    deleted=True if threadData[3] == "t" else False,
                    merged_thread_id=threadData[4],
                    merged_post_id=threadData[5],
                    is_visible=True if threadData[6] == "t" else False,
                )
            )
            self.conn.execute(
                insert(forum_thread_cat).values(
                    thread_id=thread_id,
                    category_id=threadData[7],
                )
            )
        self.conn.commit()
        counter = 1
        for postData in f_data[0]:
            if postData[10] != "f" and postData[10] != "":
                # In posts.txt, if the 10th column is f or empty, then no attachment is added.
                # If anything else is in the column, then it will be treated as the file name.
                attachment_path = os.path.join(
                    self.course_path,
                    "forum_attachments",
                    str(postData[0]),
                    str(counter),
                )
                os.makedirs(attachment_path)
                path_to_forum = os.path.join(
                    self.course_path, "forum_attachments", str(postData[0])
                )
                os.system(f"chown -R submitty_php:sample_tas_www {path_to_forum}")
                copyfile(
                    os.path.join(SETUP_DATA_PATH, "forum", "attachments", postData[10]),
                    os.path.join(attachment_path, postData[10]),
                )
            counter += 1
            self.conn.execute(insert(forum_posts).values(
                thread_id=postData[0],
                parent_id=postData[1],
                author_user_id=postData[2],
                content=postData[3],
                timestamp=postData[4],
                anonymous=True if postData[5] == "t" else False,
                deleted=True if postData[6] == "t" else False,
                endorsed_by=postData[7],
                resolved=True if postData[8] == "t" else False,
                type=postData[9],
                has_attachment=True if postData[10] != "f" else False,
                render_markdown=True if postData[11] == "t" else False,
            ))
        self.conn.commit()
