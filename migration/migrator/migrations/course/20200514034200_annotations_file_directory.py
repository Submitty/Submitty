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
    #php_user = config.submitty_users['php_user']

    # get course group
    #stat_info = os.stat(str(course_dir))
    #course_group_id = stat_info.st_gid
    #course_group = grp.getgrgid(course_group_id)[0]
    
    #Go down to version directory.
    for dir in os.listdir(annotations_dir):
        for dirSecond in os.listdir(Path(annotations_dir, dir)):
            for dirThrid in os.listdir(Path(annotations_dir, dir, dirSecond)):
                for dirPath, dirName, files in os.walk(Path(annotations_dir, dir, dirSecond, dirThrid)):
                    for name in files:
                        if "_" in name:
                            file_name = name.split('_', 1)[0]
                            grader_id = name.split('_', 1)[1]
                            path_to_hash = dirPath.replace(dirThrid, "")
                            #Hash folder + file_name + grader_id where folder is the directory structure after the version directory
                            md5_file_name = hashlib.md5((path_to_hash + file_name + grader_id).encode())
                            print(str(md5_file_name))
                            print(str(dirThrid))
                            copyfile(Path(dirPath,name), Path(annotations_dir, dir, dirSecond, dirThrid, md5_file_name.hexdigest() + '.json'))
                            #os.remove(Path(dirPath,name))
                            #file_name_quoted = "\'" + str(file_name_path) + "\'" 
                            #os.system("chown -R "+php_user+":"+course_group+ " "+ file_name_quoted)
                            #os.system("chmod -R u+rwx "+file_name_quoted)
                            #os.system("chmod -R g+rxs "+file_name_quoted)
                            #os.system("chmod -R o-rwx "+file_name_quoted)
                
                
def down(config, database, semester, course):
    pass
    #course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    #annotations_dir = Path(course_dir, 'annotations')
    
    # create the directories
    #php_user = config.submitty_users['php_user']

    # get course group
    #stat_info = os.stat(str(course_dir))
    #course_group_id = stat_info.st_gid
    #course_group = grp.getgrgid(course_group_id)[0]
    
    #for dirPath, dirName, files in os.walk(annotations_dir):
    #    for name in files:
    #        if Path(dirPath).parent.parent.parent.parent.name == 'annotations':
    #            file_name = Path(dirPath).name
    #            grader_id = name
    #            new_file_name = file_name + "_" + grader_id
    #            copyfile(Path(dirPath,name), Path(Path(dirPath).parent,new_file_name))
    #            file_name_quoted = "\'" + str(Path(Path(dirPath).parent,new_file_name)) + "\'"
    #            os.system("chown -R "+php_user+":"+course_group+ " "+  file_name_quoted)
    #            os.system("chmod -R u+rwx "+file_name_quoted)
    #            os.system("chmod -R g+rxs "+file_name_quoted)
    #            os.system("chmod -R o-rwx "+file_name_quoted)
    #            rmtree(dirPath)
            
