from pathlib import Path


def _replace(old, new):
    installed_migrations = Path(__file__).resolve().parent.parent.parent.parent.parent.parent / 'migrations' / 'course'
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


def up():
    _replace("(conn)", "(conn, semester, course)")


def down():
    _replace("(conn, semester, course)", "(conn)")
