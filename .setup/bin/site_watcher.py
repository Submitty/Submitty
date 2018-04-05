#!/usr/bin/env python3

import grp
import os
import pwd
import shutil
import time

from watchdog.observers.polling import PollingObserver as Observer
from watchdog.events import FileSystemEventHandler

CURRENT_PATH = os.path.realpath(os.path.dirname(os.path.realpath(__file__)))
SITE_PATH = os.path.realpath(os.path.join(CURRENT_PATH, '..', '..', 'site'))
INSTALL_PATH = os.path.realpath(os.path.join(CURRENT_PATH, '..', '..', '..', 'site'))

HWPHP_UID = pwd.getpwnam('hwphp').pw_uid
HWPHP_GID = grp.getgrnam('hwphp').gr_gid


def set_permissions(path):
    os.chown(path, HWPHP_UID, HWPHP_GID)
    if os.path.isdir(path):
        os.chmod(path, 0o551)
    else:
        os.chmod(path, 0o440)


class FileHandler(FileSystemEventHandler):
    def __init__(self, install_path, site_path):
        super(FileSystemEventHandler, self).__init__()
        self.install_path = install_path
        self.site_path = site_path

    def on_created(self, event):
        if event.src_path.endswith('.swp'):
            return
        print(event)

    def on_deleted(self, event):
        if event.src_path.endswith('.swp'):
            return
        print(event)

    def on_modified(self, event):
        if event.src_path.endswith('.swp') or event.is_directory:
            return

        # Only do stuff for files that aren't the tmp ones created by filesystem
        print(event)
        dst = event.src_path.replace(self.site_path, self.install_path)
        shutil.copy2(event.src_path, dst)
        set_permissions(dst)

    def on_moved(self, event):
        if event.src_path.endswith('.swp'):
            return
        print(event)


def main():
    if int(os.getuid()) != 0:
        raise SystemExit("ERROR: this script should be run as root")

    observer = Observer()
    observer.schedule(event_handler=FileHandler(INSTALL_PATH, SITE_PATH), path=SITE_PATH, recursive=True)
    observer.start()
    try:
        print("Watching {} for changes...".format(SITE_PATH))
        while True:
            time.sleep(1)
    finally:
        observer.stop()
        observer.join()

if __name__ == "__main__":
    main()
