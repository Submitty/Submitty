#!/usr/bin/env python3

from argparse import ArgumentParser
import os
from pathlib import Path
from subprocess import run
import sys
import time

from watchdog.observers.polling import PollingObserver as Observer
from watchdog.events import FileSystemEventHandler

CURRENT_PATH = Path(__file__).resolve().parent
SETUP_PATH = Path(CURRENT_PATH, '..').resolve()
GIT_PATH = Path(CURRENT_PATH, '..', '..').resolve()


class FileHandler(FileSystemEventHandler):
    def __init__(self, setup_path, helper='site'):
        super(FileSystemEventHandler, self).__init__()
        self.setup_path = setup_path
        self.helper = helper

    def run_installer(self, event):
        installer = Path(self.setup_path, 'INSTALL_SUBMITTY_HELPER_{}.sh'.format(self.helper.upper()))
        if event.src_path.endswith('.swp') or event.src_path == '.DS_Store':
            return
        run([str(installer)], stdout=sys.stdout, stderr=sys.stderr)

    def on_created(self, event):
        self.run_installer(event)

    def on_deleted(self, event):
        self.run_installer(event)

    def on_modified(self, event):
        if event.is_directory:
            return
        self.run_installer(event)

    def on_moved(self, event):
        self.run_installer(event)


def main():
    if int(os.getuid()) != 0:
        raise SystemExit("ERROR: this script should be run as root")

    parser = ArgumentParser(description='Watch a directory and install the code')
    parser.add_argument('section', type=str, choices=['site', 'bin'])
    args = parser.parse_args()

    observer = Observer()
    if args.section == 'site':
        observer.schedule(event_handler=FileHandler(GIT_PATH, 'site'), path=str(Path(GIT_PATH, 'site')), recursive=True)
    elif args.section == 'bin':
        observer.schedule(event_handler=FileHandler(GIT_PATH, 'bin'), path=str(Path(GIT_PATH, 'bin')), recursive=True)
        observer.schedule(event_handler=FileHandler(GIT_PATH, 'sbin'), path=str(Path(GIT_PATH, 'sbin')), recursive=True)

    observer.start()
    try:
        print("Watching {} for changes...".format(args.section))
        while True:
            time.sleep(1)
    finally:
        observer.stop()
        observer.join()


if __name__ == "__main__":
    main()
