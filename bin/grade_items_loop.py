#!/usr/bin/env python3

import sys


def log_message():

    
def log_error(jobname,message):
    log_message("",jobname,"","","ERROR: "+message)
    print ("ERROR :",jobname,":",message)

def log_exit(jobname,message):
    log_error(jobname,message)
    log_error(jobname,"EXIT grade_items_loop.py")
