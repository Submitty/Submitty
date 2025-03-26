import unittest
import subprocess
import os
from pathlib import Path
from time import sleep, time
import threading

def read_output(pipe, output_list):
    """
    Read lines from a subprocess pipe and append them to the output list.
    """
    for line in iter(pipe.readline, ''):
        output_list.append(line.strip())

class TestCodeWatcher(unittest.TestCase):
    def setUp(self):
        """
        Set up the necessary environment for testing.
        """
        self.test_dir = Path("/usr/local/submitty/GIT_CHECKOUT/Submitty/")
        self.setup_dir = Path(self.test_dir, ".setup")
        self.site_dir = Path(self.test_dir, "site")
        self.bin_dir = Path(self.test_dir, "bin")
        self.sbin_dir = Path(self.test_dir, "sbin")

        # Ensure directories exist
        for directory in [self.setup_dir, self.site_dir, self.bin_dir, self.sbin_dir]:
            if not directory.exists():
                self.skipTest(f"Required directory {directory} does not exist.")

        self.script_path = Path("/usr/local/submitty/GIT_CHECKOUT/Submitty/.setup/bin/code_watcher.py")
        self.process_pids = []  # Record subprocess PIDs

    def tearDown(self):
        """
        Clean up the temporary changes and terminate any remaining processes.
        """
        # Kill processes started during the test
        for pid in self.process_pids:
            try:
                os.kill(pid, 9)
            except ProcessLookupError:
                pass

        # Remove test files from watched directories
        for directory in [self.site_dir, self.bin_dir, self.sbin_dir]:
            for file in directory.iterdir():
                if file.is_file() and file.name.startswith("dummy"):
                    file.unlink()

        self.kill_remaining_processes()

    def kill_remaining_processes(self):
        """
        Ensure no related processes are left running.
        """
        result = subprocess.run(["ps", "aux"], capture_output=True, text=True)
        for line in result.stdout.splitlines():
            if "code_watcher.py" in line:
                pid = int(line.split()[1])
                try:
                    os.kill(pid, 9)
                    print(f"Killed remaining process with PID: {pid}")
                except ProcessLookupError:
                    pass

    def test_code_watcher_runs(self):
        """
        Test that the code watcher starts and reacts to file changes.
        """
        if not self.script_path.exists():
            self.skipTest("code_watcher.py script not found")

        # Start the code watcher script
        process = subprocess.Popen(
            ["sudo", "python3", str(self.script_path)],
            cwd=str(self.test_dir),
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True
        )
        self.process_pids.append(process.pid)  # Record PID
        print(f"Started code_watcher.py with PID: {process.pid}")
        print(f"Monitoring directory: {self.site_dir}")

        try:
            # Simulate a file creation event in the 'site' directory
            dummy_file = self.site_dir / "dummy.txt"
            with open(dummy_file, "w") as f:
                f.write("Test content")
            print(f"Created file: {dummy_file}")

            # Simulate a file modification event
            with open(dummy_file, "a") as f:
                f.write("\nAdditional content")
            print(f"Modified file: {dummy_file}")

            # Simulate a file deletion event
            dummy_file.unlink()
            print(f"Deleted file: {dummy_file}")

            # Read output in real-time
            output = []
            thread = threading.Thread(target=read_output, args=(process.stdout, output))
            thread.start()

            # Wait for the process to react with a timeout
            timeout = 10
            start_time = time()
            while time() - start_time < timeout:
                if any("Running INSTALL_SUBMITTY_HELPER_SITE.sh" in line for line in output):
                    print("Code watcher successfully detected file change and triggered script.")
                    break
                sleep(0.5)
            else:
                print("Code watcher did not detect file change within timeout.")
                self.fail("The code watcher did not react to the file change within the timeout")

            thread.join()
        finally:
            # Terminate the process if it's still running
            if process.poll() is None:
                process.terminate()
                try:
                    process.wait(timeout=5)
                except subprocess.TimeoutExpired:
                    process.kill()
            if process.stdout:
                process.stdout.close()
            if process.stderr:
                process.stderr.close()

if __name__ == "__main__":
    unittest.main()
