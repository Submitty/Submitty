"""Migration for the Submitty system."""
import pkg_resources
import subprocess
import sys

def up(config):
    #get existing installed packages and check if opencv-python is there
    #https://stackoverflow.com/questions/44210656/how-to-check-if-a-module-is-installed-in-python-and-if-not-install-it-within-t    
    installed = {pkg.key for pkg in pkg_resources.working_set}
    if 'opencv-python' in installed:
        print("Uninstalling opencv-python")
        try:
            subprocess.check_call("python3 -m pip uninstall opencv-python --yes --no-input", shell=True, stderr=subprocess.STDOUT)
        except subprocess.CalledProcessError as e:
            print("Failed to uninstall opencv-python, quitting", file=sys.stderr)
            raise e
    try:       
        subprocess.check_call("python3 -m pip install opencv-python-headless==4.6.0.66", shell=True, stderr=subprocess.STDOUT)
    except subprocess.CalledProcessError as e:
        print("Failed to install opencv-python-headless, quitting", file=sys.stderr)
        raise e

def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
