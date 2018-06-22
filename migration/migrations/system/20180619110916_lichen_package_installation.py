import os

def up():

    # c/c++ tokenizer
    os.system("sudo apt-get -qqy install python-clang-3.8")
    os.system("sudo pip install clang")
    os.system("sudo pip3 install clang")

    # python tokenzier
    os.system("sudo pip3 install parso")

    # permissions on pip
    os.system("sudo chmod -R 555 /usr/local/lib/python*/*")
    os.system("sudo chmod 555 /usr/lib/python*/dist-packages")
    os.system("sudo chmod 500 /usr/local/lib/python*/dist-packages/pam.py*")

    pass


def down():
    pass
