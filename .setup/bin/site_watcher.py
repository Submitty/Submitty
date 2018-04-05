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
HWCGI_UID = pwd.getpwnam('hwcgi').pw_uid
HWCGI_GID = grp.getgrnam('hwcgi').gr_gid


def set_permissions(path):
    os.chown(path, HWPHP_UID, HWPHP_GID)
    if os.path.isdir(path):
        os.chmod(path, 0o551)
    else:
        if path.endswith('.php'):
            os.chmod(path, 0o440)
        elif path.endswith('.js'):
            os.chmod(path, 0o445)
        elif path.endswith('.cgi'):
            os.chown(path, HWCGI_UID, HWCGI_GID)
            os.chmod(path, 0o540)
        else:
            os.chmod(path, 0o444)



class FileHandler(FileSystemEventHandler):
    def __init__(self, install_path, site_path):
        super(FileSystemEventHandler, self).__init__()
        self.install_path = install_path
        self.site_path = site_path

    def on_created(self, event):
        if event.src_path.endswith('.swp') or event.src_path == '.DS_Store':
            return
        print(event)
        install_path = event.src_path.replace(self.site_path, self.install_path)
        install_dir = install_path if event.is_directory else os.path.dirname(install_path)
        folders = []
        full_path = '/'
        for path in install_dir.split('/'):
            os.path.join(full_path, path)
            if not os.path.isdir(full_path):
                folders.append(full_path)
        os.makedirs(install_dir, exist_ok=True)
        if not event.is_directory:
            shutil.copy2(event.src_path, install_path)
        for folder in folders:
            set_permissions(folder)
        set_permissions(install_path)

    def on_deleted(self, event):
        if event.src_path.endswith('.swp') or event.src_path == '.DS_Store':
            return
        print(event)
        install_path = event.src_path.replace(self.site_path, self.install_path)
        if event.is_directory:
            shutil.rmtree(install_path, ignore_errors=True)
        else:
            os.unlink(install_path)

    def on_modified(self, event):
        if event.src_path.endswith('.swp') or event.is_directory or event.src_path == '.DS_Store':
            return

        # Only do stuff for files that aren't the tmp ones created by filesystem
        print(event)
        dst = event.src_path.replace(self.site_path, self.install_path)
        shutil.copy2(event.src_path, dst)
        set_permissions(dst)

    def on_moved(self, event):
        if event.src_path.endswith('.swp') or event.src_path == '.DS_Store':
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
