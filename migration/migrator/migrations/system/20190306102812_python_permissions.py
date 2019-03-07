from pathlib import Path
import subprocess


def up(config):
    for path in Path('/usr/local/lib').glob('python*'):
        subprocess.call(['find', Path(path, 'dist-packages'), '-type', 'd', '-exec', 'chmod', '755', '{}', '+'])
        subprocess.call(['find', Path(path, 'dist-packages'), '-type', 'f', '-exec', 'chmod', '755', '{}', '+'])
        subprocess.call(['find', Path(path, 'dist-packages'), '-type', 'f', '-name', '*.py*', '-exec', 'chmod', '644', '{}', '+'])
        if Path(path, 'dist-packages', 'pam.py').exists():
            subprocess.call(['chown', 'root:staff', Path(path, 'dist-packages', 'pam.py')])
