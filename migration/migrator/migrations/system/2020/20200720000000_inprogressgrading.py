import os
import shutil
from pathlib import Path


def up(config):
    before = Path(config.submitty['submitty_data_dir'], 'grading')
    after = Path(config.submitty['submitty_data_dir'], 'in_progress_grading')

    if not os.path.isdir(before):
        raise SystemExit("ERROR: grading directory does not exist")
    if os.path.isdir(after):
        raise SystemExit("ERROR: in_progress_grading directory already exists")

    shutil.move(before,after)


def down(config):
    before = Path(config.submitty['submitty_data_dir'], 'in_progress_grading')
    after = Path(config.submitty['submitty_data_dir'], 'grading')

    if not os.path.isdir(before):
        raise SystemExit("ERROR: in_progress_grading directory does not exist")
    if os.path.isdir(after):
        raise SystemExit("ERROR: grading directory already exists")

    shutil.move(before,after)

