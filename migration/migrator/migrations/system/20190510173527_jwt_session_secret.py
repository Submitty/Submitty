"""Migration for the Submitty system."""
import json
from pathlib import Path
import secrets
import shutil
import string


def up(config):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    if 'php_user' in config.submitty_users:
        secrets_path = Path(config.config_path, 'secrets_submitty_php.json')
        if not secrets_path.exists():
            characters = string.ascii_letters + string.digits
            secret_dict = {
                'session': ''.join(secrets.choice(characters) for _ in range(64))
            }
            with secrets_path.open('w') as open_file:
                json.dump(secret_dict, open_file, indent=2)
            secrets_path.chmod(0o440)
            shutil.chown(str(secrets_path), 'root', config.submitty_users['php_user'])
