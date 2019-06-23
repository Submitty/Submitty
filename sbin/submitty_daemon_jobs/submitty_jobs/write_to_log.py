#!/usr/bin/env python3

"""Logging module for bulk uploads."""

import fcntl
import json
import os


def write_to_log(log_file_path, msg, newline=True):
    """Lock and write to a given file, creates the file if it doesn't exist."""
    # create log file descriptor if it doesn't exist, if it does continue as normal
    log_fd = ''
    try:
        # create the log file descriptor and lock it
        log_fd = os.open(log_file_path, os.O_CREAT | os.O_EXCL | os.O_WRONLY)
        fcntl.flock(log_fd, fcntl.LOCK_EX)

        # write to to the file, this will unlock when we're done with it
        with os.fdopen(log_fd, 'w') as file:
            file.write(msg)
    except Exception:
        # log file exists, open it in append mode and lock it
        log_fd = os.open(log_file_path, os.O_APPEND | os.O_WRONLY)
        fcntl.flock(log_fd, fcntl.LOCK_EX)

        with os.fdopen(log_fd, 'a') as file:
            if newline:
                msg = "\n" + msg
            file.write(msg)


def write_to_json(json_path, json_data):
    """Lock and update a json file, create it if doesn't exist."""
    json_fd = ''
    try:
        json_fd = os.open(json_path, os.O_CREAT | os.O_EXCL | os.O_WRONLY)
        fcntl.flock(json_fd, fcntl.LOCK_EX)

        with os.fdopen(json_fd, 'w') as file:
            json.dump(json_data, file, sort_keys=True, indent=4)
    except Exception:
        json_fd = os.open(json_path, os.O_APPEND | os.O_RDWR)
        fcntl.flock(json_fd, fcntl.LOCK_EX)

        # json file exists, update previous data and write back
        with os.fdopen(json_fd, 'r+') as file:
            prev_data = json.load(file)
            prev_data.update(json_data)

            file.truncate(0)
            json.dump(prev_data, file, sort_keys=True, indent=4)
