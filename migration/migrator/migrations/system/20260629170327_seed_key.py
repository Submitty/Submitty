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
        if secrets_path.exists():
            with secrets_path.open('r') as open_file:
                secret_dict = json.load(open_file)
            
            if 'server' not in secret_dict:
                characters = string.ascii_letters + string.digits
                secret_dict['server'] = ''.join(secrets.choice(characters) for _ in range(64))
                
                with secrets_path.open('w') as open_file:
                    json.dump(secret_dict, open_file, indent=2)
                secrets_path.chmod(0o440)
                shutil.chown(str(secrets_path), 'root', config.submitty_users['php_user'])
        else:
            raise FileNotFoundError(f"Could not find secrets file at {secrets_path}")

def down(config):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    """
    pass
