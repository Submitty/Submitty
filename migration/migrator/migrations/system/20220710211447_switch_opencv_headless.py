"""Migration for the Submitty system."""
import pkg_resources
import os

def up(config):
    #get existing installed packages and check if opencv-python is there
    #https://stackoverflow.com/questions/44210656/how-to-check-if-a-module-is-installed-in-python-and-if-not-install-it-within-t    
    installed = {pkg.key for pkg in pkg_resources.working_set}
    if 'opencv-python' in installed:
        print("Uninstalling opencv-python")
        os.system("pip3 uninstall opencv-python --yes --no-input")

    os.system("pip3 install pip install opencv-python-headless==4.6.0.66")

def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
