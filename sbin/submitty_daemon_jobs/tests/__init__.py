# For relative imports to work in Python 3.6
import sys
import os

sys.path.append('../submitty_daemon_jobs/')

os.environ["PYTEST"] = "true"