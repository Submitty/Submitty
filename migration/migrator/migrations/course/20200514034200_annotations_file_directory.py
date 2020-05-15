import os
import grp
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
    
    for dirPath, dirName, files in os.walk(annotations_dir):
        for name in files:
            if "_" in name:
                file_name = name.split('_', 1)[0]
                grader_id = name.split('_', 1)[1]
                file_name_path = Path(dirPath, file_name);
                if not os.path.exists(file_name_path):
                    os.makedirs(str(file_name_path), exist_ok=True)
                copyfile(Path(dirPath,name), Path(file_name_path,grader_id))
                os.remove(Path(dirPath,name))
                os.system("chown -R "+php_user+":"+course_group+ str(file_name_path))
                os.system("chmod -R u+rwx "+str(file_name_path))
                os.system("chmod -R g+rxs "+str(file_name_path))
                os.system("chmod -R o-rwx "+str(file_name_path))
                
                
def down(config, database, semester, course):
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    annotations_dir = Path(course_dir, 'annotations')
    
    # create the directories
    php_user = config.submitty_users['php_user']

    # get course group
    stat_info = os.stat(str(course_dir))
    course_group_id = stat_info.st_gid
    course_group = grp.getgrgid(course_group_id)[0]
    
    for dirPath, dirName, files in os.walk(annotations_dir):
        for name in files:
            print(Path(dirPath).parent.parent.parent.parent.name)
            if Path(dirPath).parent.parent.parent.parent.name == 'annotations':
                file_name = Path(dirPath).name
                grader_id = name
                new_file_name = file_name + "_" + grader_id
                copyfile(Path(dirPath,name), Path(Path(dirPath).parent,new_file_name))
                os.system("chown -R "+php_user+":"+course_group+ str(Path(Path(dirPath).parent,new_file_name)))
                os.system("chmod -R u+rwx "+str(Path(Path(dirPath).parent,new_file_name)))
                os.system("chmod -R g+rxs "+str(Path(Path(dirPath).parent,new_file_name)))
                os.system("chmod -R o-rwx "+str(Path(Path(dirPath).parent,new_file_name)))
                rmtree(dirPath)
            
