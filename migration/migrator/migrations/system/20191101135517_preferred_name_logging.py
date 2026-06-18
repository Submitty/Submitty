"""Migration for the Submitty system."""
import re
import os
import pwd
import shutil
from pathlib import Path


def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """

    # User 'postgres' uid and gid are needed to permit postgresql access to files.
    postgres_uid = pwd.getpwnam('postgres').pw_uid
    postgres_gid = pwd.getpwnam('postgres').pw_gid

    # Update repos to ensure we have the latest SysadminTools release
    update_repos_script = str(Path(config.submitty['submitty_repository'], '.setup', 'bin', 'update_repos.sh'))
    os.system('bash ' + update_repos_script + ' >/dev/null &2>1')

    # New log folders
    psql_log_folder = str(Path(config.submitty['submitty_data_dir'], 'logs', 'psql'))
    pfn_log_folder = str(Path(config.submitty['submitty_data_dir'], 'logs', 'preferred_names'))

    try:
        os.mkdir(psql_log_folder)
        os.chown(psql_log_folder, postgres_uid, config.submitty_users['daemon_gid'], follow_symlinks=False)
        os.chmod(path=psql_log_folder, mode=0o2770)
    except FileExistsError:
        pass
    except Exception as e:
        raise SystemExit("Error trying to create postgresql log directory.\n" + str(e))

    try:
        os.mkdir(pfn_log_folder)
        os.chown(pfn_log_folder, config.submitty_users['daemon_uid'], config.submitty_users['daemon_gid'], follow_symlinks=False)
    except FileExistsError:
        pass
    except Exception as e:
        raise SystemExit("Error trying to create preferred_names log directory.\n" + str(e))

    # Postgresql configuration
    psql_version = os.popen("psql -V | grep -m 1 -o '[0-9]\+' | head -1").read().translate({0x0a: None})
    psql_conf_file = str(Path('/', 'etc', 'postgresql', psql_version, 'main', 'postgresql.conf'))
    backup_conf_file = str(Path('/', 'etc', 'postgresql', psql_version, 'main', 'postgresql.conf.backup'))

    try:
        shutil.copy2(psql_conf_file, backup_conf_file, follow_symlinks=False)
    except shutil.SameFileError:
        pass
    except Exception as e:
        raise SystemExit("Error backing up original psql configuration.\n" + str(e))

    try:
        psql_conf_fh = open(psql_conf_file, mode='r')
        psql_config = psql_conf_fh.read()
        psql_conf_fh.close()
    except Exception as e:
        raise SystemExit("Error trying to read postgresql.conf for updating.\n" + str(e))

    # Regexs used to adjust Postgresql's configuration via match and substitute.
    # Dict key is regex, value is associated replacement text.
    # Third pattern:  x5b = '[', x5d = ']', x22 = double quotes
    # Fourth pattern: x2d = '-', x2e = '.'
    # Ninth pattern:  x2d = '-'
    patterns = {r"^#*\s*log_destination\s*=\s*'[a-z]+'":                               "log_destination = 'csvlog'",
                r"^#*\s*logging_collector\s*=\s*[a-z01]+":                             "logging_collector = on",
                r"^#*\s*log_directory\s*=\s*'[^(){}<>|:;&#=!'~?*$`\x5b\x5d\x22\s]+'": f"log_directory = '{config.submitty['submitty_data_dir']}/logs/psql'",
                r"^#*\s*log_filename\s*=\s*'[a-zA-Z0-9_%\x2d\x2e]+'":                  "log_filename = 'postgresql_%Y-%m-%dT%H%M%S.log'",
                r"^#*\s*log_file_mode\s*=\s*[0-9]+":                                   "log_file_mode = 0640",
                r"^#*\s*log_rotation_age\s*=\s*[a-z0-9]+":                             "log_rotation_age = 1d",
                r"^#*\s*log_rotation_size\s*=\s*[a-zA-Z0-9]+":                         "log_rotation_size = 0",
                r"^#*\s*log_min_messages\s*=\s*[a-z]+":                                "log_min_messages = warning",
                r"^#*\s*log_min_duration_statement\s*=\s*[0-9\x2d]+":                  "log_min_duration_statement = -1",
                r"^#*\s*log_statement\s*=\s*'[a-z]+'":                                 "log_statement = 'ddl'",
                r"^#*\s*log_error_verbosity\s*=\s*[a-z]+":                             "log_error_verbosity = default"}

    for regex, replacement in patterns.items():
        psql_config = re.sub(regex, replacement, psql_config, count = 1, flags = re.MULTILINE)

    try:
        psql_conf_fh = open(psql_conf_file, mode='w')
        psql_conf_fh.write(psql_config)
        psql_conf_fh.close()
        os.chown(psql_conf_file, postgres_uid, postgres_gid, follow_symlinks=False)
    except Exception as e:
        raise SystemExit("Error trying to write updates to postgresql.conf.\n" + str(e))

    # Copy preferred_name_logging.php from SysadminTools to sbin
    pfn_script_src = str(Path(config.submitty['submitty_repository'], '..', 'SysadminTools', 'preferred_name_logging', 'preferred_name_logging.php'))
    pfn_script_dst = str(Path(config.submitty['submitty_install_dir'], 'sbin', 'preferred_name_logging.php'))

    try:
        shutil.copyfile(pfn_script_src, pfn_script_dst, follow_symlinks=False)
        os.chown(pfn_script_dst, 0, config.submitty_users['daemon_gid'], follow_symlinks=False)
        os.chmod(pfn_script_dst, 0o0550)
    except shutil.SameFileError:
        pass
    except Exception as e:
        raise SystemExit("Error trying to copy preferred_name_logging.php to sbin.\n" + str(e))

    print("A sysadmin needs to restart postgresql so the adjusted configuration is active.")

def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """

    # Remove preferred_name_logging.php from sbin
    pfn_script_file = str(Path(config.submitty['submitty_install_dir'], 'sbin', 'preferred_name_logging.php'))

    try:
        os.remove(pfn_script_file)
    except Exception as e:
        raise SystemExit("Could not delete preferred_name_logging.php from sbin.\n" + str(e))

    # Restore original postgresql.conf from backup.
    psql_version = os.popen("psql -V | grep -m 1 -o '[0-9]\+' | head -1").read().translate({0x0a: None})
    psql_conf_file = str(Path('/', 'etc', 'postgresql', psql_version, 'main', 'postgresql.conf'))
    backup_conf_file = str(Path('/', 'etc', 'postgresql', psql_version, 'main', 'postgresql.conf.backup'))

    try:
        shutil.copy2(backup_conf_file, psql_conf_file, follow_symlinks=False)
    except shutil.SameFileError:
        pass
    except Exception as e:
        raise SystemExit("Error restoring original psql configuration.\n" + str(e))

    print("A sysadmin needs to restart postgresql so the reverted configuration is active.")

    # Do NOT remove log folders, so to preserve any existing logs.
    # Should we re-up, error checking will elegantly handle "file exists" exceptions.

# EOF
