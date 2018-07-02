#!/usr/bin/env python3

"""
Job handler for hwcron. It watches ${SUBMITTY_DATA_DIR}/hwcron_job_queue for
new files which should be structured:
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

from . import QUEUE_DIR, HWCRON_USER
from . import jobs

from watchdog.observers import Observer
from watchdog.events import FileCreatedEvent, FileSystemEventHandler


class NewFileHandler(FileSystemEventHandler):
    def __init__(self, queue):
        """

        :param multiprocessing.Queue queue:
        """
        self.queue = queue

    def on_created(self, event):
        if isinstance(event, FileCreatedEvent):
            if not Path(event.src_path).name.startswith('PROCESSING_'):
                self.queue.put(os.path.basename(event.src_path))


def process_queue(queue):
    """

    :param queue:
    :type queue: multiprocessing.Queue
    :return:
    """
    while True:
        job = queue.get(True)
        process_job(job)


def process_job(job):
    """

    :param str job:
    """

    with open(os.path.join(QUEUE_DIR, job)) as job_file:
        job_details = json.load(job_file)
    processing_job = QUEUE_DIR / ('PROCESSING_' + job)

    Path(QUEUE_DIR, job).rename(processing_job)
    try:
        job_class = getattr(jobs, job_details['job'])(job_details)
        job_class.run_job()
    except NameError:
        # function does not exist
        pass
    processing_job.unlink()


def cleanup_job(job):
    """
    Cleanup jobs that were in the middle of processing when hwcron_jobs_handler
    was killed.

    :param str job:
    :return:
    """
    with Path(QUEUE_DIR, job).open() as job_file:
        job_details = json.load(job_file)
    try:
        job_class = getattr(jobs, job_details['job'])(job_details)
        job_class.cleanup_job()
    except NameError:
        pass
    old_name = job.split('PROCESSING_')[1]
    Path(QUEUE_DIR, job).rename(Path(QUEUE_DIR, old_name))


def main():
    if pwd.getpwuid(os.getuid()).pw_name != HWCRON_USER:
        raise SystemExit('ERROR! This script must be run by hwcron!')

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

    print(queue.qsize())
    observer = Observer()
    observer.schedule(NewFileHandler(queue), str(QUEUE_DIR))
    observer.start()

    pool = multiprocessing.Pool(5, process_queue, (queue, ))
    try:
        while True:
            time.sleep(1)
    finally:
        pool.terminate()
        observer.stop()
        observer.join()
        pool.join()


if __name__ == '__main__':
    main()

