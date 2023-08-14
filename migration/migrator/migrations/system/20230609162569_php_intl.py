"""Migration for the Submitty system."""
import os

def up(config):
    """
    Install PHP Intl module for localization.
    """
    os.system("apt-get install -qqy php-intl")

def down(config):
    pass