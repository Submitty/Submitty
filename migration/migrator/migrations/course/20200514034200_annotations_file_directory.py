import os
import grp
import hashlib
import shutil
from pathlib import Path
import json
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
    for gradeable_level_dir in os.scandir(annotations_dir):
        for user_level_dir in os.scandir(Path(annotations_dir, gradeable_level_dir)):
            for version_level_dir in os.scandir(Path(annotations_dir, gradeable_level_dir, user_level_dir)):
                for annotation_full_path, annotation_name, files in os.walk(Path(annotations_dir, gradeable_level_dir, user_level_dir, version_level_dir)):
                    for name in files:
                        if "_" in name:
                            [file_name, grader_id] = name.split('_', 1)
                            annotation_full_path_sub = annotation_full_path.replace("annotations", "submissions");
                            #Hash folder + file_name + grader_id where folder is the directory structure after the version directory
                            json_data = {};
                            with open(Path(annotation_full_path,name)) as initial_file:
                                json_data['annotations'] = json.dump(json.load(initial_file))
                            json_data['grader_id'] = grader_id[:-5]
                            json_data['file_name'] = file_name
                            md5_file_name = hashlib.md5((annotation_full_path_sub + '/'+ file_name + '.pdf').encode())
                            file_path = Path(annotations_dir, gradeable_level_dir, user_level_dir, version_level_dir)
                            new_json_file = open(Path(annotation_full_path, md5_file_name.hexdigest() + "_" + grader_id), 'x')
                            json.dump(json_data, new_json_file)
                            new_json_file.close()
                            #os.remove(Path(annotation_full_path,name))
                            os.system("chown -R "+php_user+":"+course_group+ " "+ str(annotation_full_path))
                            os.system("chmod -R u+rwx "+str(annotation_full_path))
                            os.system("chmod -R g+rxs "+str(annotation_full_path))
                            os.system("chmod -R o-rwx "+str(annotation_full_path))
                
                
def down(config, database, semester, course):
    pass