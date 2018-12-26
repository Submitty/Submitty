"""The migrator python module."""

from pathlib import Path


VERSION = "2.0.0"
DIR_PATH = Path(__file__).parent.resolve()
MIGRATIONS_PATH = DIR_PATH / 'migrations'
ENVIRONMENTS = ['master', 'system', 'course']
