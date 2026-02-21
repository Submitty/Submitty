"""Migration for the Submitty system."""

import os

def up(config):
    """
    Install the cloc package for autograding source code comment counting.
    """

    os.system("sudo apt-get install cloc --yes")


def down(config):
    pass
