#!/usr/bin/env python3

import sys
from datetime import datetime
import os
import submitty_utils
import grade_items_logging
import grade_item
import fcntl
import glob
from multiprocessing import current_process, Pool, Queue
import time
import random
from queue import Empty
from watchdog.observers import Observer
from watchdog.events import FileCreatedEvent, FileSystemEventHandler


# ==================================================================================
# these variables will be replaced by INSTALL_SUBMITTY.sh
MAX_INSTANCES_OF_GRADE_STUDENTS_string = "__INSTALL__FILLIN__MAX_INSTANCES_OF_GRADE_STUDENTS__"
MAX_INSTANCES_OF_GRADE_STUDENTS_int    = int(MAX_INSTANCES_OF_GRADE_STUDENTS_string)

AUTOGRADING_LOG_PATH="__INSTALL__FILLIN__AUTOGRADING_LOG_PATH__"
SUBMITTY_DATA_DIR = "__INSTALL__FILLIN__SUBMITTY_DATA_DIR__"
HWCRON_UID = "__INSTALL__FILLIN__HWCRON_UID__"
INTERACTIVE_QUEUE = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_interactive")
BATCH_QUEUE = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_batch")


# ==================================================================================
class NewFileHandler(FileSystemEventHandler):
    """
    Simple handler for watchdog that watches for new files
    """

    def __init__(self, queue):
        super(FileSystemEventHandler, self).__init__()
        self.queue = queue

    def on_created(self, event):
        if isinstance(event, FileCreatedEvent):
            if os.path.basename(event.src_path).startswith("GRADING_") is False:
                self.queue.put(event.src_path)


# ==================================================================================
def initialize(untrusted_queue):
    """
    Initializer function for all our processes. We get one untrusted user off our queue which
    we then set in our Process. We cannot recycle the worker process as else the untrusted user
    we set for this process will be lost.

    :param untrusted_queue: multiprocessing.queues.Queue that contains all untrusted users left to
                            assign
    """
    current_process().untrusted = untrusted_queue.get()


def runner(queue_file):
    """
    Primary method which we run on any new jobs (queue files) that we pass to an empty
    process in our pool. The process gets the path of the queue_file as a paramater, which
    we can use to get the queue (batch or interactive) that we're in as well as then operate on
    it. This function would contain all of the logic in grade_students.sh from line 784 downward.

    :param queue_file: file path pointing to the file we want to operate one.
    """

    my_dir,my_file=os.path.split(queue_file)
    pid = os.getpid()
    directory = os.path.dirname(os.path.realpath(queue_file))
    name = os.path.basename(os.path.realpath(queue_file))
    grading_file = os.path.join(directory, "GRADING_" + name)
    # noinspection PyUnresolvedReferences

    open(os.path.join(grading_file), "w").close()
    untrusted = current_process().untrusted
    grade_item.just_grade_item(my_dir, queue_file, untrusted)

    os.remove(queue_file)
    os.remove(grading_file)


def job_error_callback(exception):
    """
    Callback for if our callable was to raise an exception. We would want to implement this
    function so that it would clean up anything left behind when a queued task failed to run as
    well as log the exception we got, while also removing the errored queue file from the pool.
    Because this is not designed to run as a cron task, we would probably have to implement
    sending an email here as well.

    :param exception:
    """

    grade_items_logging.log_message(False,"","","","","job error callback")

    pass



def job_callback(result):
    """
    Callback for our job when it successfully runs. We would handle killing off any
    runaway aspects of the grading job (such as forked child processes). We would also
    move cleaning up the queue files here as well (just have to return the filename from runner).

    :param result:
    """

    grade_items_logging.log_message(False,"","","","","job callback")

    pass


def populate_queue(queue, folder):
    """
    Populate a queue with all files in folder. We first scan the folder to check for any
    "GRADING_*" and clean them up, and then add the remaining files (except for any that
    start with '.') to our queue.

    :param queue: multiprocessing.queues.Queue
    :param folder: string representing the path to the folder to add files from
    """
    for file_path in glob.glob(os.path.join(folder, "GRADING_*")):
        # We would add a real cleanup routine here for Submitty
        print("Cleanup " + file_path)
        os.remove(file_path)

    # TODO: We should use os.scandir() here and get the timestamp of the file so that we can
    # generate a sorted list (by timestamp) and then put the files into the queue in that order
    for filename in os.listdir(folder):
        if not filename.startswith("."):
            queue.put(os.path.join(folder, filename))


# ==================================================================================
# ==================================================================================
def main():

    # verify the hwcron user is running this script
    if not int(os.getuid()) == int(HWCRON_UID):
        raise SystemExit("ERROR: the grade_item.py script must be run by the hwcron user")

    grade_items_logging.log_message(False,"","","","","grade_scheduler.py launched")

    # Set up our queues that we're going to monitor for new jobs to run on
    interactive_queue = Queue()
    batch_queue = Queue()
    populate_queue(interactive_queue, INTERACTIVE_QUEUE)
    populate_queue(batch_queue, BATCH_QUEUE)

    untrusted_users = Queue()
    for i in range(MAX_INSTANCES_OF_GRADE_STUDENTS_int):
        untrusted_users.put("untrusted" + str(i).zfill(2))

    interactive_handler = NewFileHandler(interactive_queue)
    batch_handler = NewFileHandler(batch_queue)

    # Setup watchdog observer that will watch the folders and run the handler on any
    # FileSystemEvents. This runs in a thread automatically.
    observer = Observer()
    observer.schedule(event_handler=interactive_handler, path=INTERACTIVE_QUEUE,
                      recursive=False)
    observer.schedule(event_handler=batch_handler, path=BATCH_QUEUE, recursive=False)
    observer.start()

    print ("max instances",MAX_INSTANCES_OF_GRADE_STUDENTS_string,
           MAX_INSTANCES_OF_GRADE_STUDENTS_int)


    print("watching directory...")
    with Pool(processes=MAX_INSTANCES_OF_GRADE_STUDENTS_int, initializer=initialize, initargs=(untrusted_users,)) as pool:
        print("pool created...")
        try:
            while True:
                job = None
                # We have to block around trying to get elements from both queues (with preference
                # on interactive), so we cannot block within the get() method for one queue.
                while job is None:
                    pid = os.getpid()
                    print (pid,"in loop   iq=",interactive_queue.qsize(),"  bq=",batch_queue.qsize())
                    try:
                        if interactive_queue.empty() is False:
                            job = interactive_queue.get()
                        elif batch_queue.empty() is False:
                            job = batch_queue.get()
                        else:
                            time.sleep(1)
                    # protect on the cases where empty() was not reliable and returned incorrectly
                    except Empty:
                        pass
                # this runs the process async (non-blocking) so we rely on the callbacks/prints
                # for knowing when this particular function finishes and the process becomes
                # available again
                pool.apply_async(func=runner, args=(job, ), callback=job_callback,
                                 error_callback=job_error_callback)
        except KeyboardInterrupt:
            observer.stop()
            pool.close()
        finally:
            observer.join()
            pool.join()

    grade_items_logging.log_message(False,"","","","","grade_scheduler.py terminated")


# ==================================================================================
if __name__ == "__main__":
    main()
