import traceback
from pathlib import Path
import sys
try:
    from watchdog.observers import Observer
    from watchdog.events import FileSystemEventHandler
except ImportError as err:
    print("\nwatchdog python library not installed correctly\n")
    traceback.print_exc()


class EventHandler(FileSystemEventHandler):
    def on_created(self, event):
        #self.run_installer(event)
        print(event.src_path)

    def on_deleted(self, event):
        #self.run_installer(event)
        pass

    def on_modified(self, event):
        #self.run_installer(event)
        print(event)
        print("!!!")

    def on_moved(self, event):
        #self.run_installer(event)
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

    try:
        while True:
            pass
    except KeyboardInterrupt:
        observer.stop()

    observer.join()