"""
This file contains functions that are dependent on other functions in Utils
and the functions in this file should not be used in any other files in Utils.
This is done to avoid circular imports.

This file contains an:
    import from create_or_generate with functions:
        add_to_group
        commit_submission_to_repo
"""


import os
from tempfile import TemporaryDirectory

from sample_courses import SUBMITTY_DATA_DIR
from sample_courses.utils.create_or_generate import create_gradeable_submission, create_group


def add_to_group(group, user_id):
    """
    Adds the user to the specified group, creating the group if it does not exist.
    :param group:
    :param user_id:
    """
    create_group(group)
    os.system("usermod -a -G {} {}".format(group, user_id))


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
        os.system("git commit -a --allow-empty -m 'adding submission files"
                  f"' --author='{user_id} <{user_id}@example.com>'")
        os.system('git push')
    os.chdir(my_cwd)
