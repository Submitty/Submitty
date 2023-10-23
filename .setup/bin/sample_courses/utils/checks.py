
import pwd
import grp


def user_exists(user) -> bool:
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


def group_exists(group) -> bool:
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
