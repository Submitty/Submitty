import os
import subprocess


def up(config):
    os.system("apt-get install libzbar0 --yes")
    
    os.system("pip3 install pyzbar")
    os.system("pip3 install pdf2image")

    os.system("sudo chmod -R 555 /usr/local/lib/python*/*")
    os.system("sudo chmod 555 /usr/lib/python*/dist-packages")
    os.system("sudo chmod 500 /usr/local/lib/python*/dist-packages/pam.py*")

def down(config):
    pass
