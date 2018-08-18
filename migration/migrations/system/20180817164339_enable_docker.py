import os
import json
import shutil
from pathlib import Path
from subprocess import call

# The changes in this pull request change the interface/arguments to
# the autograding scripts, thus it requires that all gradeables be
# rebuilt/recompiled


def up(config):

    # ============================================================
    # rebuild all gradeables
    from pathlib import Path
    Path(config.submitty['submitty_install_dir'], 'REBUILD_ALL_FLAG.txt').touch()

    # ============================================================
    # create the basic docker image for python & c++ 

    tmp_docker_dir = Path("/tmp/docker")
    shutil.rmtree(str(tmp_docker_dir), ignore_errors=True)
    os.makedirs(str(tmp_docker_dir))
    
    dockerfile = Path(config.submitty['submitty_repository'],".setup","Dockerfile")
    shutil.copy(str(dockerfile),str(Path(tmp_docker_dir,"Dockerfile")))
    shutil.copytree(str(Path(config.submitty['submitty_install_dir'],"drmemory")),str(Path(tmp_docker_dir,"drmemory")))
    shutil.copytree(str(Path(config.submitty['submitty_install_dir'],"SubmittyAnalysisTools")),str(Path(tmp_docker_dir,"SubmittyAnalysisTools")))

    submitty_users_filename = str(Path(config.submitty['submitty_install_dir'], 'config', 'submitty_users.json'))
    with open (submitty_users_filename,"r") as open_file:
        my_json = json.load(open_file)
        daemon_uid = my_json["daemon_uid"]
        daemon_user = my_json["daemon_user"]
        daemon_gid = my_json["daemon_gid"]

    os.chown(str(tmp_docker_dir),daemon_uid,daemon_gid)
    for root, dirs, files in os.walk(str(tmp_docker_dir)):
        for d in dirs:  
            os.chown(os.path.join(root, d),daemon_uid,daemon_gid)
        for f in files:
            os.chown(os.path.join(root, f),daemon_uid,daemon_gid)
    
    print ("GOING TO BUILD DOCKER IMAGE")
    os.chdir(str(tmp_docker_dir))
    call(["su","-c","docker build -t ubuntu:custom -f Dockerfile .",daemon_user])
    print ("ALL DONE WITH DOCKER SETUP")

    
def down(config):

    # ============================================================
    # rebuild all gradeables
    from pathlib import Path
    Path(config.submitty['submitty_install_dir'], 'REBUILD_ALL_FLAG.txt').touch()

    pass
