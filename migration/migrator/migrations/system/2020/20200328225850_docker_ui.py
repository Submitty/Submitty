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
   
    #add submitty_cgi to docker group
    os.system("usermod -a -G docker " + config.submitty_users['cgi_user'] )

    #allow php to read the autograding containers config
    gid = grp.getgrnam( "submitty_daemonphp" ).gr_gid

    tgt_dir = os.path.join(config.submitty['submitty_install_dir'], 'config', 'autograding_containers.json')
    os.chown(tgt_dir, gid, config.submitty_users['php_gid'])


def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
