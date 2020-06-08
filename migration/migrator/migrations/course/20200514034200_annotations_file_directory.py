import os
import grp
import hashlib

from pathlib import Path
from shutil import copyfile
from shutil import rmtree
def up(config, database, semester, course):
      
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    annotations_dir = Path(course_dir, 'annotations')
    
    # create the directories
    php_user = config.submitty_users['php_user']

    # get course group
    stat_info = os.stat(str(course_dir))
    course_group_id = stat_info.st_gid
    course_group = grp.getgrgid(course_group_id)[0]
    
    #Go down to version directory.
    for dir in os.listdir(annotations_dir):
        for dirSecond in os.listdir(Path(annotations_dir, dir)):
            for dirThrid in os.listdir(Path(annotations_dir, dir, dirSecond)):
                for dirPath, dirName, files in os.walk(Path(annotations_dir, dir, dirSecond, dirThrid)):
                    for name in files:
                        if "_" in name:
                            file_name = name.split('_', 1)[0]
                            grader_id = name.split('_', 1)[1]
                            #Hash folder + file_name + grader_id where folder is the directory structure after the version directory
                            md5_file_name = hashlib.md5((dirPath + file_name).encode())
                            file_path = Path(annotations_dir, dir, dirSecond, dirThrid)
                            copyfile(Path(dirPath,name), Path(dirPath, md5_file_name.hexdigest() + "_" + grader_id + '.json'))
                            os.remove(Path(dirPath,name))
                            os.system("chown -R "+php_user+":"+course_group+ " "+ str(dirPath))
                            os.system("chmod -R u+rwx "+str(dirPath))
                            os.system("chmod -R g+rxs "+str(dirPath))
                            os.system("chmod -R o-rwx "+str(dirPath))
                
                
def down(config, database, semester, course):
    pass
            
