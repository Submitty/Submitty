"""Migration for the Submitty system."""
import os
import pwd
import grp

def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    uid = pwd.getpwnam("submitty_daemon").pw_uid
    gid = grp.getgrnam("submitty_php").gr_gid
    tgt_dir = os.path.join(config.submitty['submitty_data_dir'], 'docker_data')

    if not os.path.exists(tgt_dir):
        os.mkdir(tgt_dir)
        os.chmod(tgt_dir, 0o2770)

        os.chown(tgt_dir, uid, gid)

    tgt_dir = os.path.join(config.submitty['submitty_data_dir'], 'logs', 'docker_interface_logs')
    
    if not os.path.exists(tgt_dir):
        os.mkdir(tgt_dir)
        os.chmod(tgt_dir, 0o2770)

    tgt_dir = os.path.join(config.submitty['submitty_install_dir'], 'config', 'autograding_containers.json')
    os.chown(tgt_dir, uid, gid)


def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
