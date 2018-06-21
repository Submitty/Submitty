#!/usr/bin/env python3
"""
Script that when run will monitor a given directory and anytime there's a change in it, run the
appropriate INSTALL_SUBMITTY_HELPER_*.sh script. This should be run from within Vagrant.

It currently supports watching the following top-level directories:
- site
- bin
- sbin

usage: code_watcher.py [-h]

Watch a directory and install the code

optional arguments:
  -h, --help  show this help message and exit
"""

from argparse import ArgumentParser
import os
from pathlib import Path
from subprocess import run
import sys

from watchdog.observers.polling import PollingObserver as Observer
from watchdog.events import FileSystemEventHandler


class FileHandler(FileSystemEventHandler):
    def __init__(self, setup_path, helper='site'):
        super(FileSystemEventHandler, self).__init__()
        self.setup_path = setup_path
        self.helper = helper

    def run_installer(self, event):
        print('Running INSTALL_SUBMITTY_HELPER_{}.sh'.format(self.helper.upper()))
        installer = Path(self.setup_path, 'INSTALL_SUBMITTY_HELPER_{}.sh'.format(self.helper.upper()))
        if event.src_path.endswith('.swp') or event.src_path == '.DS_Store':
            return
        run(["bash", str(installer)], stdout=sys.stdout, stderr=sys.stderr)
        print("DONE\n")

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
    args = parser.parse_args()

    current_path = Path(__file__).resolve().parent
    setup_path = Path(current_path, '..').resolve()
    git_path = Path(current_path, '..', '..').resolve()

    observer = Observer()
    observer.schedule(FileHandler(setup_path, 'site'), str(Path(git_path, 'site')), True)
    observer.schedule(FileHandler(setup_path, 'bin'), str(Path(git_path, 'bin')), True)
    observer.schedule(FileHandler(setup_path, 'bin'), str(Path(git_path, 'sbin')), True)

    observer.start()
    try:
        print("Watching the following folders for change:")
        print("    - site")
        print("    - bin")
        print("    - sbin")
        print()
        input("~~Hit enter to exit~~\n")
    finally:
        observer.stop()
        observer.join()


if __name__ == "__main__":
    main()
