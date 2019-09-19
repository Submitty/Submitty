"""Migration for the Submitty system."""
import os
import pwd
from pathlib import Path
from pprint import pprint

def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """

    # Update repos to ensure we have the latest SysadminTools release
    update_repos_script = str(Path(config.submitty['submitty_repository'], '.setup', 'bin', 'update_repos.sh'))
    os.system('bash ' + update_repos_script)

    # New log folders
    psql_log_folder = str(Path(config.submitty['submitty_data_dir'], 'logs', 'psql'))
    pfn_log_folder = str(Path(config.submitty['submitty_data_dir'], 'logs', 'preferred_names'))
    postgres_uid = pwd.getpwnam('postgres').pw_uid
    try:
        os.mkdir(psql_log_folder)
        os.chown(psql_log_folder, postgres_uid, config.submitty_users['daemon_gid'])
    except FileExistsError:
        pass

    try:
        os.mkdir(pfn_log_folder)
        os.chown(pfn_log_folder, config.submitty_users['daemon_uid'], config.submitty_users['daemon_gid'])
    except FileExistsError:
        pass

    # Postgresql configuration (FIX ME)
    psql_version = os.popen("psql -V | grep -m 1 -o '[[:digit:]]\+' | head -1").read().translate({0x0a: None})
    process = f"""\
sed -i "s~^#*[ tab]*log_destination[ tab]*=[ tab]*'[a-z]\+'~log_destination = 'csvlog'~;
        s~^#*[ tab]*logging_collector[ tab]*=[ tab]*[a-z01]\+~logging_collector = on~;
        s~^#*[ tab]*log_directory[ tab]*=[ tab]*'[^][(){{}}<>|:;&#=!'?\*\~\$\"\` tab]\+'~log_directory = '{config.submitty['submitty_data_dir']}/logs/psql'~;
        s~^#*[ tab]*log_filename[ tab]*=[ tab]*'[-a-zA-Z0-9_%\.]\+'~log_filename = 'postgresql-%Y-%m-%d_%H%M%S.log'~;
        s~^#*[ tab]*log_file_mode[ tab]*=[ tab]*[0-9]\+~log_file_mode = 0640~;
        s~^#*[ tab]*log_rotation_age[ tab]*=[ tab]*[a-z0-9]\+~log_rotation_age = 1d~;
        s~^#*[ tab]*log_rotation_size[ tab]*=[ tab]*[a-zA-Z0-9]\+~log_rotation_size = 10MB~;
        s~^#*[ tab]*log_min_messages[ tab]*=[ tab]*[a-z]\+~log_min_messages = log~;
        s~^#*[ tab]*log_min_duration_statement[ tab]*=[ tab]*[-0-9]\+~log_min_duration_statement = 0~;
        s~^#*[ tab]*log_line_prefix[ tab]*=[ tab]*'.\+'~log_line_prefix = '%t '~" /etc/postgresql/{psql_version}/main/postgresql.conf"""
    subprocess.run(process)

    #TO DO: copy preferred_name_logging.php to sbin and set ownership+permissions



def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
