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


def add_to_group(group, user_id) -> None:
    """
    Adds the user to the specified group, creating the group if it does not exist.
    :param group:
    :param user_id:
    """
    create_group(group)
    os.system(f"usermod -a -G {group} {user_id}")


def commit_submission_to_repo(user_id, src_file, repo_path, vcs_subdirectory) -> None:
    # a function to commit and push a file to a user's submitty-hosted repository
    my_cwd = os.getcwd()
    with TemporaryDirectory() as temp_dir:
        os.chdir(temp_dir)
        os.system("chown -R submitty_daemon "+temp_dir)
        os.system(f"su -c 'git clone {SUBMITTY_DATA_DIR}/vcs/git/{repo_path}' submitty_daemon")
        os.chdir(os.path.join(temp_dir, user_id))
        os.system("su -c 'git checkout main' submitty_daemon")
        os.system("su -c 'git pull' submitty_daemon")
        # use the above function to copy the files into the git repo for us
        dst: str = os.getcwd()
        if vcs_subdirectory != '':
            dst = os.path.join(dst, vcs_subdirectory)

        create_gradeable_submission(src_file, dst)
        os.system("chown -R submitty_daemon "+temp_dir)
        os.system("su -c 'git add --all' submitty_daemon")
        os.system("su -c 'git config user.email \'user@example.com\'' submitty_daemon")
        os.system("su -c 'git config user.name \'username\'' submitty_daemon")
        my_command = ("git commit -a --allow-empty -m \"adding submission files\" "
                      "--author=\"username <user@example.com>\"")
        os.system("su -c '"+my_command+"' submitty_daemon")
        os.system("su -c 'git push' submitty_daemon")
    os.chdir(my_cwd)
