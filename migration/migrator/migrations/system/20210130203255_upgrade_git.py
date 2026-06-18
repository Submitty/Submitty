# upgrade git to 2.28.0 or greater
# https://git-scm.com/docs/git-init/2.28.0
# need the --initial-branch option
# for creating student submission vcs git repositories

import os

def up(config):
    os.system("add-apt-repository ppa:git-core/ppa -y")
    os.system("apt-get install git -y")
    pass

def down(config):
    pass
