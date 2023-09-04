
import grp
import json
import os
import pwd
import random
import shutil
import string
import subprocess
import uuid
from datetime import datetime
from tempfile import TemporaryDirectory

from sample_courses import *

def get_random_text_from_file(filename):
    line = ""
    with open(os.path.join(SETUP_DATA_PATH, 'random', filename)) as comment:
        line = next(comment)
        for num, alternate_line in enumerate(comment):
            if random.randrange(num + 2):
                continue
            line = alternate_line
    return line.strip()


def generate_random_user_id(length=15):
    return ''.join(random.choice(string.ascii_lowercase + string.ascii_uppercase + string.digits) for _ in range(length))


def generate_random_ta_comment():
    return get_random_text_from_file('TAComment.txt')


def generate_random_ta_note():
    return get_random_text_from_file('TANote.txt')


def generate_random_student_note():
    return get_random_text_from_file('StudentNote.txt')

def generate_pronouns():
    pronoun_num = random.random()
    if pronoun_num <= .05: 
        pronoun_list = ["Ze/Zir","Xe/Xem", "Ne/Nem", "Vi/Vir", "Ne/Nir" "Nix/Nix", "Xy/Xyr", "Zhe/Zhim"]
        return random.choice(pronoun_list)
    elif pronoun_num <= .30:
        return ""
    elif pronoun_num <= .60:
        return "She/Her"
    elif pronoun_num <= .70:
        return "They/Them"
    else: return "He/Him"

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


def load_data_json(file_name):
    """
    Loads json file from the .setup/data directory returning the parsed structure
    :param file_name: name of file to load
    :return: parsed JSON structure from loaded file
    """
    file_path = os.path.join(SETUP_DATA_PATH, file_name)
    if not os.path.isfile(file_path):
        raise IOError("Missing the json file .setup/data/{}".format(file_name))
    with open(file_path) as open_file:
        json_file = json.load(open_file)
    return json_file


def load_data_yaml(file_path):
    """
    Loads yaml file from the .setup/data directory returning the parsed structure
    :param file_path: name of file to load
    :return: parsed YAML structure from loaded file
    """
    if not os.path.isfile(file_path):
        raise IOError("Missing the yaml file {}".format(file_path))
    with open(file_path) as open_file:
        yaml_file = yaml.load(open_file)
    return yaml_file


def user_exists(user):
    """
    Checks to see if the user exists on the linux file system. We can use this to delete a user
    so that we can recreate them which avoids users having left over data from a previous run of
    setting up the sample courses.
    :param user: string to check if user exists
    :return: boolean on if user exists or not
    """
    try:
        pwd.getpwnam(user)
        return True
    except KeyError:
        return False


def group_exists(group):
    """
    Checks to see if the group exists on the linux file system so that we don't try to create
    groups that already exist.

    :param group: string to check if group exists
    :return: boolean on if group exists or not
    """
    try:
        grp.getgrnam(group)
        return True
    except KeyError:
        return False


def create_group(group):
    """
    Creates the group on the system, adding some base users to the group as well that are necessary
    for the system to function and are not defined within the users.yml file.
    :param group: name of the group to create
    """
    if not group_exists(group):
        os.system("groupadd {}".format(group))

    if group == "sudo":
        return


def add_to_group(group, user_id):
    """
    Adds the user to the specified group, creating the group if it does not exist.
    :param group:
    :param user_id:
    """
    create_group(group)
    os.system("usermod -a -G {} {}".format(group, user_id))


def get_php_db_password(password):
    """
    Generates a password to be used within the site for database authentication. The password_hash
    function (http://php.net/manual/en/function.password-hash.php) generates us a nice secure
    password and takes care of things like salting and hashing.
    :param password:
    :return: password hash to be inserted into the DB for a user
    """
    proc = subprocess.Popen(
        ["php", "-r", "print(password_hash('{}', PASSWORD_DEFAULT));".format(password)],
        stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    (out, err) = proc.communicate()
    return out.decode('utf-8')


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


def create_gradeable_submission(src, dst):
    """
    Given a source and a destination, copy the files from the source to the destination. First, before
    copying, we check if the source is a directory, if it is, then we zip the contents of this to a temp
    zip file (stored in /tmp) and store the path to this newly created zip as our new source.

    At this point, (for all uploads), we check if our source is a zip (by just checking file extension is
    a .zip), then we will extract the contents of the source (using Shutil) to the destination, else we
    just do a simple copy operation of the source file to the destination location.

    At this point, if we created a zip file (as part of that first step), we remove it from the /tmp directory.

    :param src: path of the file or directory we want to use for this submission
    :type src: str
    :param dst: path to the folder where we should copy the submission to
    :type src: str
    """
    zip_dst = None
    if os.path.isdir(src):
        zip_dst = os.path.join("/tmp", str(uuid.uuid4()))
        zip_dst = shutil.make_archive(zip_dst, 'zip', src)
        src = zip_dst

    if src[-3:] == "zip":
        shutil.unpack_archive(src, dst)
    else:
        shutil.copy(src, dst)

    if zip_dst is not None and isinstance(zip_dst, str):
        os.remove(zip_dst)

def create_pdf_annotations(file_name, file_path, src, dst, grader_id):
    """
    Specifically designed helper function that copies a annotation from the source to the destination.
    The source annotation need to be modified to reflect:
        the file that the annotations belongs to
        the grader that is responsible for the annotation

    :param file_name: encoded file name
    :type src: str
    :param file_path: anonymous file path
    :type src: str
    :param src: path of the file or directory we want to use for this annotation
    :type src: str
    :param dst: path to the folder where we should copy the annotation to
    :type src: str
    :param grader_id: grader of the annotation
    :type src: str
    """
    with open(src, 'r') as open_file:
        annotation_json = json.load(open_file)
        annotation_json['file_path'] = file_path
        annotation_json['grader_id'] = grader_id
        for annotation in annotation_json['annotations']:
            annotation['userId'] = grader_id

    with open(os.path.join(dst, file_name), 'w') as f:
        json.dump(annotation_json, f, indent = 2)

def commit_submission_to_repo(user_id, src_file, repo_path, vcs_subdirectory):
    # a function to commit and push a file to a user's submitty-hosted repository
    my_cwd = os.getcwd()
    with TemporaryDirectory() as temp_dir:
        os.chdir(temp_dir)
        os.system(f'git clone {SUBMITTY_DATA_DIR}/vcs/git/{repo_path}')
        os.chdir(os.path.join(temp_dir, user_id))
        os.system('git checkout main')
        os.system('git pull')
        # use the above function to copy the files into the git repo for us
        dst = os.getcwd()
        if vcs_subdirectory != '':
            dst = os.path.join(dst, vcs_subdirectory)
        
        create_gradeable_submission(src_file, dst)
        os.system('git add --all')
        os.system(f"git config user.email '{user_id}@example.com'")
        os.system(f"git config user.name '{user_id}'")
        os.system(f"git commit -a --allow-empty -m 'adding submission files' --author='{user_id} <{user_id}@example.com>'")
        os.system('git push')
    os.chdir(my_cwd)

def mimic_checkout(repo_path, checkout_path, vcs_subdirectory):
    os.system(f'git clone {SUBMITTY_DATA_DIR}/vcs/git/{repo_path} {checkout_path}/tmp -b main')
    if vcs_subdirectory != '':
        if vcs_subdirectory[0] == '/':
            vcs_subdirectory = vcs_subdirectory[1:]
        file_path = os.path.join(f'{checkout_path}/tmp', vcs_subdirectory)
    else:
        file_path = os.path.join(f'{checkout_path}/tmp')

    shutil.copytree(file_path, f'{checkout_path}', dirs_exist_ok=True)
    shutil.rmtree(f'{checkout_path}/tmp')