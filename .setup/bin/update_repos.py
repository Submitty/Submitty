#!/usr/bin/env python3

"""
Pull new code from the 3 auxiliary repos.
"""

import os

if os.path.isdir("/usr/local/submitty/GIT_CHECKOUT/Tutorial"):
    os.chdir("/usr/local/submitty/GIT_CHECKOUT/Tutorial")
    os.system("git pull origin master")

if os.path.isdir("/usr/local/submitty/GIT_CHECKOUT/AnalysisTools"):
    os.chdir("/usr/local/submitty/GIT_CHECKOUT/AnalysisTools")
    os.system("git pull origin master")

if os.path.isdir("/usr/local/submitty/GIT_CHECKOUT/Lichen"):
    os.chdir("/usr/local/submitty/GIT_CHECKOUT/Lichen")
    os.system("git pull origin master")
