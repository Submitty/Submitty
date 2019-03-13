"""Run the migrator tool through its CLI."""
from pathlib import Path
import sys
from migrator import get_dir_path, cli

if __name__ == '__main__':
    config_path = Path(get_dir_path(), '..', '..', '..', 'config')
    config_path = config_path.resolve() if config_path.exists() else None
    cli.run(sys.argv[1:], config_path)
