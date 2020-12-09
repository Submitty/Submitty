"""Run the migrator tool through its CLI."""
from pathlib import Path
import sys
from migrator import cli

if __name__ == '__main__':
    config_path = Path(Path(__file__).parent.resolve(), '..', '..', '..', 'config')
    print()
    print(config_path)
    print(config_path.exists())
    config_path = config_path.resolve() if config_path.exists() else None
    cli.run(sys.argv[1:], config_path)
