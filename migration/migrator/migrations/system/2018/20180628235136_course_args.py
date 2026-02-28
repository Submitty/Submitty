from pathlib import Path


def _replace(install_path, old, new):
    installed_migrations = install_path / 'migrations' / 'course'
    if installed_migrations.is_dir():
        for entry in installed_migrations.iterdir():
            if entry.is_dir() or not entry.name.endswith('.py'):
                continue
            with entry.open('r+') as open_file:
                data = open_file.read()
                data = data.replace(old, new)
                open_file.seek(0)
                open_file.write(data)
                open_file.truncate()


def up(config):
    _replace(Path(config.submitty['submitty_install_dir']), '(conn)', '(conn, semester, course)')


def down(config):
    _replace(Path(config.submitty['submitty_install_dir']), '(conn, semester, course)', '(conn)')
