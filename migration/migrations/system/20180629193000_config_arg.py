from pathlib import Path


def _replace(migration_path, old, new):
    if migration_path.is_dir():
        for entry in migration_path.iterdir():
            if entry.is_dir() or not entry.name.endswith('.py'):
                continue
            with entry.open('r+') as open_file:
                data = open_file.read()
                data = data.replace(old, new)
                open_file.seek(0)
                open_file.write(data)
                open_file.truncate()


def up(config):
    migration_path = Path(config.submitty['submitty_install_dir'], 'migrations')
    _replace(migration_path /'system', 'up()', 'up(config)')
    _replace(migration_path / 'system', 'down()', 'down(config)')
    for folder in ('course', 'master'):
        _replace(migration_path / folder, 'up(conn', 'up(config, conn')
        _replace(migration_path / folder, 'down(conn', 'down(config, conn')


def down(config):
    migration_path = Path(config.submitty['submitty_install_dir'], 'migrations')
    _replace(migration_path / 'system', 'up(config)', 'up()')
    _replace(migration_path / 'system', 'down(config)', 'down()')
    for folder in ('course', 'master'):
        _replace(migration_path / folder, 'up(config, conn', 'up(conn')
        _replace(migration_path / folder, 'down(config, conn', 'down(conn')
