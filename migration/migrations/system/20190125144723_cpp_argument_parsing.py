import os
import shutil

def up(config):
    
    TCLAP_VERSION = '1.2.2'
    TCLAP_TAR = 'tclap-{0}.tar.gz'.format(TCLAP_VERSION)
    TCLAP_DIR = os.path.join('/tmp','tclap-{0}'.format(TCLAP_VERSION))

    os.chdir("/tmp")
    os.system("wget https://sourceforge.net/projects/tclap/files/{0} -o /dev/null > /dev/null 2>&1".format(TCLAP_TAR))
    os.system("tar -xpzf {0}".format(TCLAP_TAR))
    os.remove(TCLAP_TAR)
    os.chdir(TCLAP_DIR)
    os.system("sed -i 's/SUBDIRS = include examples docs tests msc config/SUBDIRS = include docs msc config/' Makefile.in")
    os.system("bash configure")
    os.system("make")
    os.system("make install")
    os.chdir('/tmp')
    shutil.rmtree(TCLAP_DIR)

def down(config):
    pass