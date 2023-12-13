
import json
import os
import random
import shutil
import string
import uuid

from sample_courses.utils import get_random_text_from_file
from sample_courses.utils.checks import group_exists


def create_gradeable_submission(src: str, dst: str) -> None:
    """
    Given a source and a destination, copy the files from the source to the destination. First,
    before copying, we check if the source is a directory, if it is, then we zip the contents of
    this to a temp zip file (stored in /tmp) and store the path to this newly created zip as
    our new source.

    At this point, (for all uploads), we check if our source is a zip (by just checking file
    extension is a .zip), then we will extract the contents of the source (using Shutil) to
    the destination, else we just do a simple copy operation of the source file to the
    destination location.

    At this point, if we created a zip file (as part of that first step),
    we remove it from the /tmp directory.

    :param src: path of the file or directory we want to use for this submission
    :type src: str
    :param dst: path to the folder where we should copy the submission to
    :type src: str
    """
    zip_dst = None
    if os.path.isdir(src):
        zip_dst: str = os.path.join("/tmp", str(uuid.uuid4()))
        zip_dst: str = shutil.make_archive(zip_dst, 'zip', src)
        src = zip_dst

    if src[-3:] == "zip":
        shutil.unpack_archive(src, dst)
    else:
        shutil.copy(src, dst)

    if zip_dst is not None and isinstance(zip_dst, str):
        os.remove(zip_dst)


def create_pdf_annotations(file_name: str, file_path: str, src: str, dst: str, grader_id) -> None:
    """
    Specifically designed helper function that copies a annotation from the
    source to the destination.

    The source annotation need to be modified to reflect:
        the file that the annotations belongs to
        the grader that is responsible for the annotation

    :param file_name: encoded file name
    :param file_path: anonymous file path
    :param src: path of the file or directory we want to use for this annotation
    :param dst: path to the folder where we should copy the annotation to
    :param grader_id: grader of the annotation
    """
    with open(src, 'r') as open_file:
        annotation_json = json.load(open_file)
        annotation_json['file_path'] = file_path
        annotation_json['grader_id'] = grader_id
        for annotation in annotation_json['annotations']:
            annotation['userId'] = grader_id

    with open(os.path.join(dst, file_name), 'w') as f:
        json.dump(annotation_json, f, indent=2)


def create_group(group) -> None:
    """
    Creates the group on the system, adding some base users to the group as well that are necessary
    for the system to function and are not defined within the users.yml file.
    :param group: name of the group to create
    """
    if not group_exists(group):
        os.system(f"groupadd {group}")

    if group == "sudo":
        return


def generate_random_user_id(length: int = 15) -> str:
    return ''.join(random.choice(string.ascii_lowercase + string.ascii_uppercase
                                 + string.digits) for _ in range(length))


def generate_random_ta_comment() -> str:
    return get_random_text_from_file('TAComment.txt')


def generate_random_ta_note() -> str:
    return get_random_text_from_file('TANote.txt')


def generate_random_student_note():
    return get_random_text_from_file('StudentNote.txt')


def generate_pronouns() -> str:
    pronoun_num = random.random()
    if pronoun_num <= .05:
        pronoun_list = ["Ze/Zir", "Xe/Xem", "Ne/Nem", "Vi/Vir",
                        "Ne/Nir" "Nix/Nix", "Xy/Xyr", "Zhe/Zhim"]
        return random.choice(pronoun_list)
    elif pronoun_num <= .30:
        return ""
    elif pronoun_num <= .60:
        return "She/Her"
    elif pronoun_num <= .70:
        return "They/Them"
    else:
        return "He/Him"


def generate_versions_to_submit(num=3, original_value=3):
    if num == 1:
        return original_value
    if random.random() < 0.3:
        return generate_versions_to_submit(num-1, original_value)
    else:
        return original_value-(num-1)


def generate_probability_space(probability_dict, default=0):
    """
    This function takes in a dictionary whose key is the probability (decimal less than 1),
    and the value is the outcome (whatever the outcome is).
    """
    probability_counter = 0
    target_random = random.random()
    prev_random_counter = 0
    for key in sorted(probability_dict.keys()):
        value = probability_dict[key]
        probability_counter += key
        if probability_counter >= target_random and target_random > prev_random_counter:
            return value
        prev_random_counter = probability_counter
    return default
