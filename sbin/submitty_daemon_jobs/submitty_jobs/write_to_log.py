import fcntl
import os


def write_to_log(log_file_path, msg, newline=True):
    # create log file descriptor if it doesn't exist, if it does continue as normal
    log_fd = ''
    try:
        log_fd = os.open(log_file_path, os.O_CREAT | os.O_EXCL | os.O_WRONLY)
        fcntl.flock(log_fd, fcntl.LOCK_EX)

        with os.fdopen(log_fd, 'w') as file:
            file.write(msg)
    except Exception:
        log_fd = os.open(log_file_path, os.O_APPEND | os.O_WRONLY)
        fcntl.flock(log_fd, fcntl.LOCK_EX)

        with os.fdopen(log_fd, 'a') as file:
            if newline:
                msg = "\n" + msg
            file.write(msg)
