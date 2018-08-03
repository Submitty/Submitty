import os

def up(config):

    #############
    #NPM install
    #############
    os.system("curl -sL https://deb.nodesource.com/setup_8.x | sudo -E bash -")
    os.system("sudo apt-get install -y nodejs")

    pass


def down(config):
    pass
