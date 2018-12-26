import os
import json
from pathlib import Path


def up(config):
    
    DRMEM_TAG="release_2.0.1"
    DRMEM_VER="2.0.1-2"

    drmemory_dir = str(Path(config.submitty['submitty_install_dir'], 'drmemory'))
    course_builders_group = config.submitty_users['course_builders_group']
    
    os.system("rm -rf "+drmemory_dir)
    os.makedirs(drmemory_dir)
    os.chdir("/tmp")
    os.system("wget https://github.com/DynamoRIO/drmemory/releases/download/"+DRMEM_TAG+"/DrMemory-Linux-"+DRMEM_VER+".tar.gz -o /dev/null > /dev/null 2>&1")
    os.system("tar -xpzf DrMemory-Linux-"+DRMEM_VER+".tar.gz")
    os.system("rsync --delete -a /tmp/DrMemory-Linux-"+DRMEM_VER+"/ "+drmemory_dir)
    os.system("rm -rf /tmp/DrMemory*")
    os.system("chown -R root:"+course_builders_group+" "+drmemory_dir)
    os.system("chmod -R 755 "+drmemory_dir)


def down(config):
    pass
