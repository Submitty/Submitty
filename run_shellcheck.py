# This script serves as a wrapper around ShellCheck to run ShellCheck only
# files listed in the .shellcheckignore script since ShellCheck does not support
# this functionality by default

from pathlib import Path
import subprocess
import os

ignored_files = set()
with open('.shellcheckignore', 'r') as ignore_file:
    for line in ignore_file.readlines():
        ignored_files.update(Path('.').rglob(line.strip()))

all_shell_scripts = set(Path('.').rglob('*.sh'))

shell_scripts_to_check = [str(x) for x in all_shell_scripts.difference(ignored_files)]

print(['shellcheck'] + shell_scripts_to_check)

return_code = 0
for script in shell_scripts_to_check:
    process = subprocess.run(['shellcheck', script], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    print(process.stdout.decode("utf-8"))
    if process.returncode != 0:
        return_code = process.returncode

exit(return_code)
