#!/usr/bin/env python

'''
Pull new code from the 2 auxiliary repos.
'''


import os

os.chdir("/usr/local/submitty/GIT_CHECKOUT_Tutorial/")
os.system("git pull origin master")

os.chdir("/usr/local/submitty/GIT_CHECKOUT_AnalysisTools/")
os.system("git pull origin master")
