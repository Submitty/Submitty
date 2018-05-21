#!/usr/bin/env python3

"""
Job handler for hwcron. It watches ${SUBMITTY_DATA_DIR}/hwcron_job_queue for
new files which are structured:
{
    "job": "string",
    ...
}
where the job is the name of a class within hwcron_jobs.hwcron_jobs

"""
import json
import os
import time
import multiprocessing
from pathlib import Path
import pwd
import shutil

from python_submitty_jobs import submitty_jobs
from python_submitty_jobs.submitty_jobs import jobs

from watchdog.observers import Observer
from watchdog.events import FileCreatedEvent, FileSystemEventHandler


CONFIG_PATH = Path('.', '..', 'config')
with open(Path(CONFIG_PATH, 'submitty.json')) as open_file:
    JSON_FILE = json.load(open_file)
DATA_DIR = JSON_FILE['submitty_data_dir']
QUEUE_DIR = Path(DATA_DIR, 'hwcron_job_queue')

with open(Path(CONFIG_PATH, 'submitty_users.json')) as open_file:
    JSON_FILE = json.load(open_file)
HWCRON_USER = JSON_FILE['hwcron_user']

submitty_jobs.DATA_DIR = DATA_DIR


class NewFileHandler(FileSystemEventHandler):
    def __init__(self, queue):
        """

        :param multiprocessing.Queue queue:
        """
        self.queue = queue

    def on_created(self, event):
        if isinstance(event, FileCreatedEvent):
            event_path = Path(event.src_path)
            if not Path(event.src_path).parts[-1].startswith('PROCESSING_'):
                self.queue.put(os.path.basename(event.src_path))


def process_job(job):
    """

    :param str job:
    """
    with open(os.path.join(QUEUE_DIR, job)) as job_file:
        job_details = json.load(job_file)
    processing_job = 'PROCESSING_' + job
    shutil.move(os.path.join(QUEUE_DIR, job), os.path.join(QUEUE_DIR, processing_job))
    try:
        job_class = getattr(jobs, job_details['job'])(job_details)
        job_class.run_job()
    except NameError:
        # function does not exist
        pass
    os.remove(os.path.join(QUEUE_DIR, processing_job))


def cleanup_job(job):
    """
    Cleanup jobs that were in the middle of processing when hwcron_jobs_handler
    was killed.

    :param str job:
    :return:
    """
    with open(os.path.join(QUEUE_DIR, job)) as job_file:
        job_details = json.load(job_file)
    try:
        job_class = getattr(jobs, job_details['job'])(job_details)
        job_class.cleanup_job()
    except NameError:
        pass


def main():
    if pwd.getpwuid(os.getuid()).pw_name != HWCRON_USER:
        raise SystemExit('ERROR! This script must be run by hwcron!')

    os.chdir(QUEUE_DIR)

    queue = multiprocessing.Queue()
    for entry in os.scandir(QUEUE_DIR):
        if entry.is_file():
            if entry.name.startswith('PROCESSING_'):
                cleanup_job(entry.name)
                name = entry.name.split('PROCESSING_')[1]

            else:
                name = entry.name
            queue.put(name)

    observer = Observer()
    observer.schedule(NewFileHandler(queue), QUEUE_DIR)
    observer.start()

    try:
        with multiprocessing.Pool(processes=5) as pool:
            job = queue.get()
            pool.apply_async(process_job, (job,))
        while True:
            time.sleep(1)
    finally:
        observer.stop()
        observer.join()


if __name__ == '__main__':
    main()

