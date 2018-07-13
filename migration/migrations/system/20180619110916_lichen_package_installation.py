import os
import subprocess


def up(config):
    # c/c++ tokenizer
    p1 = subprocess.Popen(['clang', '--version'], stdout=subprocess.PIPE)
    p2 = subprocess.Popen(['grep', '-o', '[0-9]\.[0-9]\{1,\}'], stdin=p1.stdout, stdout=subprocess.PIPE)
    p3 = subprocess.Popen(['head', '-1'], stdin=p2.stdout, stdout=subprocess.PIPE, universal_newlines=True)
    clang_ver = p3.communicate()[0].strip()

    os.system("sudo apt-get -qqy install python-clang-{}".format(clang_ver))
    os.system("sudo pip2 install clang")
    os.system("sudo pip3 install clang")

    # python tokenzier
    os.system("sudo pip3 install parso")

    # permissions on pip
    os.system("sudo chmod -R 555 /usr/local/lib/python*/*")
    os.system("sudo chmod 555 /usr/lib/python*/dist-packages")
    os.system("sudo chmod 500 /usr/local/lib/python*/dist-packages/pam.py*")

    pass


def down(config):
    pass
