import os
import grp
import hashlib
import shutil
from pathlib import Path
import json


def get_file_path(directory, file_name):
    for root, _, files in os.walk(directory):
        for entry in files:
            if entry[:-4] == file_name:
                return os.path.abspath(os.path.join(root, entry))
    return os.path.abspath(os.path.join(directory, f'{file_name}.pdf'))


def up(config, database, semester, course):
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    annotations_dir = Path(course_dir, 'annotations')
    submissions_dir = Path(course_dir, 'submissions')
    php_user = config.submitty_users['php_user']

    stat_info = os.stat(str(course_dir))
    course_group_id = stat_info.st_gid
    course_group = grp.getgrgid(course_group_id)[0]

    for gradeable_dir in annotations_dir.iterdir():
        for user_dir in gradeable_dir.iterdir():
            for version_dir in user_dir.iterdir():
                for entry in version_dir.iterdir():
                    if "_" in entry.name:
                        [file_name, grader_id] = entry.name.rsplit('_', 1)

                        json_data = {}
                        with entry.open() as initial_file:
                            json_data['annotations'] = json.load(initial_file)
                        json_data['grader_id'] = grader_id[:-5]
                        json_data['file_path'] = get_file_path(
                            submissions_dir / gradeable_dir.name / user_dir.name / version_dir.name,
                            file_name
                        )

                        md5_file = hashlib.md5(json_data['file_path'].encode()).hexdigest()
                        new_file = version_dir / f'{md5_file}_{grader_id}'

                        with new_file.open('w') as open_file:
                            json.dump(json_data, open_file)

                        entry.unlink()
                        shutil.chown(new_file, php_user, course_group)
                        new_file.chmod(0o640)


def down(config, database, semester, course):
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    annotations_dir = Path(course_dir, 'annotations')
    php_user = config.submitty_users['php_user']

    stat_info = os.stat(str(course_dir))
    course_group_id = stat_info.st_gid
    course_group = grp.getgrgid(course_group_id)[0]

    for gradeable_dir in annotations_dir.iterdir():
        for user_dir in gradeable_dir.iterdir():
            for version_dir in user_dir.iterdir():
                for entry in version_dir.iterdir():
                    if "_" in entry.name:

                        with entry.open() as open_file:
                            json_data = json.load(open_file)

                        file_name = os.path.basename(json_data['file_path'])[:-4]
                        grader_id = json_data['grader_id']

                        new_file = Path(
                            version_dir,
                            f"{file_name}_{grader_id}.json"
                        )

                        with new_file.open('w') as open_file:
                            json.dump(json_data['annotations'], open_file)

                        entry.unlink()
                        shutil.chown(new_file, php_user, course_group)
                        new_file.chmod(0o640)
