"""A May 2026 change for Apache and php-fpm security has made the '/usr'
directory read-only for php.  Any configuration files that will be
edited through Submitty web pages / GUI need to be moved out of '/usr'.

Currently, this is just the autograding_containers.json file, which is
edited by the docker UI page to add/revise docker containers assigned
to each capability (worker VMs are committed to support capabilities
in the autograding_workers.json file.)"""

from pathlib import Path
import shutil

# The file(s) to move
files_to_move = [ "autograding_containers.json" ]


def up(config):
    install_config_dir = Path(config.submitty['submitty_install_dir']) / "config"
    data_config_dir = Path(config.submitty['submitty_data_dir']) / "config"

    data_config_dir.mkdir(exist_ok=True)
    data_config_dir.chmod(0o755)

    for f in files_to_move:
        if (install_config_dir / f).exists():
            if (data_config_dir / f).exists():
                raise RuntimeError(f+" exists in both " + str(install_config_dir)
                                   + " and " + str(data_config_dir))
            else:
                shutil.move( install_config_dir / f, data_config_dir / f)
        else:
            raise RuntimeError(f+" does not exist in " + str(install_config_dir))

def down(config):

    install_config_dir = Path(config.submitty['submitty_install_dir']) / "config"
    data_config_dir = Path(config.submitty['submitty_data_dir']) / "config"

    for f in files_to_move:
        if (data_config_dir / f).exists():
            if (install_config_dir / f).exists():
                raise RuntimeError(f+" exists in both " + str(install_config_dir)
                                   + " and " + str(data_config_dir))
            else:
                shutil.move (data_config_dir / f, install_config_dir / f)
        else:
            raise RuntimeError(f+" does not exist in " + str(data_config_dir))


