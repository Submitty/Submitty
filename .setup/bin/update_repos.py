#!/usr/bin/env python3

"""
Pull new code from the 2 auxiliary repos.
"""


import os

if os.path.isdir("/usr/local/submitty/GIT_CHECKOUT_AnalysisTools"):
    os.chdir("/usr/local/submitty/GIT_CHECKOUT_Tutorial/")
    os.system("git pull origin master")

if os.path.isdir("/usr/local/submitty/GIT_CHECKOUT_AnalysisTools"):
    os.chdir("/usr/local/submitty/GIT_CHECKOUT_AnalysisTools/")
    os.system("git pull origin master")
