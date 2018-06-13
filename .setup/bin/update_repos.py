#!/usr/bin/env python3

"""
Ensure minimum version/release/tag for the auxiliary repos.
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
