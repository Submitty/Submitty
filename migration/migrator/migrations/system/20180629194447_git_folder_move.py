import os
from pathlib import Path
import shutil


def up(config):
    vcs_dir = Path(config.submitty['submitty_data_dir'], 'vcs')
    git_dir = vcs_dir / 'git'
    if not git_dir.is_dir():
        os.makedirs(str(git_dir), exist_ok=True)
        shutil.chown(str(git_dir), 'www-data', 'www-data')
    for entry in vcs_dir.iterdir():
        if entry.name == 'git':
            pass
        shutil.move(str(entry), str(git_dir))


def down(config):
    vcs_dir = Path(config.submitty['submitty_data_dir'], 'vcs')
    git_dir = vcs_dir / 'git'
    if git_dir.is_dir():
        for entry in git_dir.iterdir():
            shutil.move(str(entry), str(vcs_dir))
        git_dir.unlink()
