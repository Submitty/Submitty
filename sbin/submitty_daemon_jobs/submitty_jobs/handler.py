#!/usr/bin/env python3

"""
Job handler for the submitty daemon user. It watches
${SUBMITTY_DATA_DIR}/daemon_job_queue for
new files which should be structured:
{
    "job": "string",
    ...
}
where the job is the name of a class within submitty_daemon_jobs.submitty_daemon_jobs
"""
import json
import os
import time
import multiprocessing
from pathlib import Path
import pwd
import shutil
from datetime import datetime
from submitty_utils import dateutils
import subprocess
import psutil

from . import QUEUE_DIR, DAEMON_USER
from . import jobs

from watchdog.observers import Observer
from watchdog.events import FileCreatedEvent, FileSystemEventHandler

NUMBER_OF_DAEMON_JOBS_WORKERS = 5


def logMessage(message):
    """
    Write a message to the submitty system logs folder to aid in debugging.
    """
    now = dateutils.get_current_time()
    now_filename = datetime.strftime(now, "%Y%m%d")
    filename = f"/var/local/submitty/logs/daemon_job_queue/{now_filename}.txt"
    pid = os.getpid()
    now_format = datetime.strftime(now, "%Y-%m-%d %H:%M:%S")
    dated_message = f"{now_format} | {pid:>7} | {message}"
    with open(filename, "a") as logfile:
        logfile.write(dated_message+"\n")
        logfile.flush()
    print(dated_message, flush=True)


class NewFileHandler(FileSystemEventHandler):
    """
    Watchdog handler for watching for creation of new files inside
    of a directory
    """
    def __init__(self, queue):
        """

        :param multiprocessing.Queue queue:
        """
        self.queue = queue

    def on_created(self, event):
        logMessage(f"on_created for new file handler {event.src_path}")
        if isinstance(event, FileCreatedEvent):
            if not Path(event.src_path).name.startswith('PROCESSING_'):
                self.queue.put(os.path.basename(event.src_path))
                logMessage(f"queue put {event.src_path}")


def process_queue(queue):
    """

    :param queue:
    :type queue: multiprocessing.Queue
    :return:
    """
    while True:
        job = queue.get(True)
        logMessage('Got job off queue: '+job)
        try:
            logMessage(f"process_queue, going to process job {job}")
            process_job(job)
            logMessage(f"process_queue, done with process job {job}")
        except Exception as e:
            logMessage('Error processing job: '+job)
            logMessage('  Exception: '+str(e))
        finally:
            logMessage(f"Finally for {job}")
            job_file = Path(QUEUE_DIR, job)
            processing_file = QUEUE_DIR / ('PROCESSING_' + job)
            if job_file.exists():
                if processing_file.exists() \
                        and job_file.stat().st_mtime != processing_file.stat().st_mtime:
                    logMessage('Job edited, rerunning job: '+job)
                    queue.put(job)
                else:
                    logMessage(f"unlink job file {job}")
                    job_file.unlink()
            if processing_file.exists():
                processing_file.unlink()
                logMessage(f"unlink job file {processing_file}")


def process_job(job):
    """

    :param str job:
    """

    logMessage("START JOB "+job)
    fullpath_job = os.path.join(str(QUEUE_DIR), job)
    fullpath_processing_job = os.path.join(str(QUEUE_DIR), ('PROCESSING_' + job))
    with open(fullpath_job) as job_file:
        job_details = json.load(job_file)
    shutil.copy2(fullpath_job, fullpath_processing_job)
    logMessage(f"ready to run job {job}")

    try:
        job_class = getattr(jobs, job_details['job'])(job_details)
        if not job_class.has_required_keys():
            logMessage("Missing some details for job: "+job)
            return
        if not job_class.validate_job_details():
            logMessage("Failed to validate details for job: "+job)
            return
        logMessage(f"run job...  {job}")
        job_class.run_job()
    except NameError:
        # function does not exist
        logMessage(f"name error when processing job ... {job}")
        pass
    logMessage("finished job: "+job)


def cleanup_job(job):
    """
    Cleanup jobs that were in the middle of processing when submitty_daemon_jobs_handler
    was killed.

    :param str job:
    :return:
    """
    logMessage(f"cleanup job {job}")
    with Path(QUEUE_DIR, job).open() as job_file:
        job_details = json.load(job_file)
    try:
        logMessage("cleanup job (will need to re-run): "+job)
        job_class = getattr(jobs, job_details['job'])(job_details)
        job_class.cleanup_job()
    except NameError:
        pass
    old_name = job.split('PROCESSING_')[1]
    Path(QUEUE_DIR, job).rename(Path(QUEUE_DIR, old_name))


def killStaleDaemonJobs():
    """
    There should only be one instance of submitty_daemon_jobs running
    at a time.  When we launch, clean up any stale processes from
    previous runs.
    """
    logMessage("Checking for stale submitty_daemon_jobs.py processes")
    # all pids for python3 processes
    python_pids = list(map(int, subprocess.check_output(["pidof", "-c", 'python3']).split()))
    # all pids for processes with "submitty_daemon_jobs.py" argument
    main_py_pids = list(map(int, subprocess.check_output(["pgrep", "-f",
                                                          "submitty_daemon_jobs.py"]).split()))
    python_main_py_pid = set(python_pids).intersection(main_py_pids)
    myself = os.getpid()
    for i in python_main_py_pid:
        if i == myself:
            # don't kill myself
            continue
        p = psutil.Process(i)
        p.terminate()
        logMessage(f"Stale submitty_daemon_jobs.py process {i} was killed")


def main():

    """
    Main runner function for the daemon process. It sets up our queue for incoming jobs
    (processing any json files that were saved while the daemon wasn't running), and
    then kicks off WatchDog to monitor for new files in the queue directory.
    """
    if pwd.getpwuid(os.getuid()).pw_name != DAEMON_USER:
        raise SystemExit('ERROR! This script must be run by the submitty daemon user!')

    logMessage("")
    logMessage("(RE-)STARTING DAEMON JOBS HANDLER")
    killStaleDaemonJobs()

    os.chdir(str(QUEUE_DIR))

    queue = multiprocessing.Queue()
    for entry in QUEUE_DIR.iterdir():
        if entry.is_file():
            if entry.name.startswith('PROCESSING_'):
                cleanup_job(entry.name)
                name = entry.name.split('PROCESSING_')[1]
            else:
                name = entry.name
            queue.put(name)

    logMessage(f"initial queue size {queue.qsize()}")
    observer = Observer()
    observer.schedule(NewFileHandler(queue), str(QUEUE_DIR))
    observer.start()

    pool = multiprocessing.Pool(NUMBER_OF_DAEMON_JOBS_WORKERS, process_queue, (queue, ))
    try:
        while True:
            # print("current queue size ", queue.qsize())
            time.sleep(1)
    finally:
        pool.terminate()
        observer.stop()
        observer.join()
        pool.join()


if __name__ == '__main__':
    main()
