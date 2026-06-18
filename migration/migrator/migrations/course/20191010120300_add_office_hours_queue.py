"""Migration for the Submitty master database."""
import json
from pathlib import Path

def up(config, database, semester, course):
    database.execute("CREATE TABLE IF NOT EXISTS queue(entry_id serial PRIMARY KEY, user_id VARCHAR(20) NOT NULL  REFERENCES users(user_id), name VARCHAR (20) NOT NULL, time_in TIMESTAMP NOT NULL, time_helped TIMESTAMP, time_out TIMESTAMP, removed_by VARCHAR (20)  REFERENCES users(user_id), status SMALLINT NOT NULL)");
    database.execute("CREATE TABLE IF NOT EXISTS queue_settings(id serial PRIMARY KEY, open boolean NOT NULL, code VARCHAR (20) NOT NULL)");

    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    # add boolean to course config
    config_file = Path(course_dir, 'config', 'config.json')
    if config_file.is_file():
        with open(config_file, 'r') as in_file:
            j = json.load(in_file)
        if 'queue_enabled' not in j['course_details']:
            j['course_details']['queue_enabled'] = False

        with open(config_file, 'w') as out_file:
            json.dump(j, out_file, indent=4)


def down(config, database, semester, course):
    pass
