from pathlib import Path


# The changes in this pull request change the interface/arguments to
# the autograding scripts, thus it requires that all gradeables be
# rebuilt/recompiled


def up(config):
    from pathlib import Path
    Path(config.submitty['submitty_install_dir'], 'REBUILD_ALL_FLAG.txt').touch()


def down(config):
    from pathlib import Path
    Path(config.submitty['submitty_install_dir'], 'REBUILD_ALL_FLAG.txt').touch()
