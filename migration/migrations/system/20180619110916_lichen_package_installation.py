import os
import subprocess


def up(config):
    # c/c++ tokenizer
    p1 = subprocess.Popen(['clang', '--version'], stdout=subprocess.PIPE)
    p2 = subprocess.Popen(['grep', '-o', '[0-9]\.[0-9]\{1,\}'], stdin=p1.stdout, stdout=subprocess.PIPE)
    p3 = subprocess.Popen(['head', '-1'], stdin=p2.stdout, stdout=subprocess.PIPE, universal_newlines=True)
    clang_ver = p3.communicate()[0].strip()

    os.system("sudo apt-get -qqy install python-clang-{}".format(clang_ver))
    os.system("sudo pip3 install clang")

    # python tokenzier
    os.system("sudo pip3 install parso")


def down(config):
    pass
