"""Migration for the Submitty system."""
import subprocess
import sys
from importlib.util import find_spec

def up(config):
    opencv_python_pkg = find_spec('opencv-python')
    if opencv_python_pkg is not None:
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
