#!/usr/bin/env python3

import traceback
from pathlib import Path
import sys
import time
import subprocess

try:
    from watchdog.observers import Observer
    #from watchdog.observers.polling import PollingObserver as Observer

    from watchdog.events import FileSystemEventHandler
except ImportError:
    print("\nwatchdog python library not installed correctly\n")
    traceback.print_exc()

class EventHandler(FileSystemEventHandler):

    def __init__(self):
            self.exlcuded = []
            for e in excludePaths:
                self.exlcuded.append(self.translateURL(e))

    def translateURL(self, url):
        return Path("/usr/local/submitty", self.getBaseURL(url) )

    def getBaseURL(self, url):
        # work backwards until we hit the Submitty root dir
        parts = Path(url).parts
        comps = []
        for part in reversed(parts):
            if part == 'Submitty':
                break
            
            comps.append(part)

        # reconstruct the string
        base = ''
        for comp in reversed(comps):
            base += comp + "/"

        return Path(base)


    def on_created(self, event):
        #create a new file
        if Path(event.src_path).name[0] == '.':
            return
        print(event)

    def on_deleted(self, event):
        #self.run_installer(event)
        #print ("deleted")
        print(event)
        pass

    def on_modified(self, event):
        if event.is_directory:
            return

        print("updating!")
        tgt = self.translateURL( event.src_path )

        print(tgt)
        if Path(event.src_path).name[0] == '.':
            return

        subprocess.run(["cp", event.src_path, str(tgt) ], stdout=sys.stdout, stderr=sys.stderr)

    def on_moved(self, event):
        if Path(event.src_path).name[0] == '.':
            return
        #print("moved")
        #self.run_installer(event)
        print(event)
        pass

if __name__ == "__main__":

    file_location = Path(__file__).absolute()
    root_path = file_location.parents[2]
    #directories to watch for changes
    target_paths = [
        Path(root_path, "site"),
        Path(root_path, "sbin"),
        Path(root_path, "bin")
    ]

    observer = Observer()
    for path in target_paths:
        observer.schedule(EventHandler(), str(path), recursive=True)

    observer.start()

    print("starting watcher!")
    try:
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        observer.stop()

    observer.join()
    print("code watcher stopped!")