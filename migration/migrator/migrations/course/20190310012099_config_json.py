import configparser
import os
import json
from pathlib import Path


def up(config, conn, semester, course):
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    old_config_file = Path(course_dir, 'config', 'config.ini')
    new_config_file = Path(course_dir, 'config', 'config.json')

    if old_config_file.is_file():
        config = configparser.ConfigParser()
        config.read(str(old_config_file))
        config_obj = dict()
        for key in filter(lambda x: x != 'DEFAULT', config.keys()):
            config_obj[key] = dict()
            for kkey in config[key]:
                val = config[key][kkey].strip().strip('"')
                l_val = val.lower()
                if val.isdigit():
                    val = int(val)
                elif l_val == "true" or l_val == 'on':
                    val = True
                elif l_val == "false" or l_val == "off":
                    val = False
                config_obj[key][kkey] = val
        with open(new_config_file, 'w') as open_file:
            json.dump(config_obj, open_file, indent=2)
        stat = os.stat(str(old_config_file))
        os.chown(str(new_config_file), stat.st_uid, stat.st_gid)
        os.chmod(str(new_config_file), 0o660)
