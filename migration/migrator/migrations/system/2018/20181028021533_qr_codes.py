import os


def up(config):
    os.system("apt-get install libzbar0 --yes")

    os.system("pip3 install pyzbar")
    os.system("pip3 install pdf2image")


def down(config):
    pass
