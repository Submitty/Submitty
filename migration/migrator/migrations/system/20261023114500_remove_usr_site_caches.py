"""Migration for the Submitty system."""

from pathlib import Path
import shutil


def up(config):
    cache_dir = Path(config.submitty["submitty_install_dir"]) / "site" / "cache"
    for name in ["twig", "routes", "doctrine", "access_control"]:
        shutil.rmtree(cache_dir / name, ignore_errors=True)


def down(config):
    pass
