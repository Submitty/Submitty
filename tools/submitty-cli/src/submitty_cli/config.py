"""Config loader: reads /submitty/config/submitty.json and submitty_admin.json."""
from __future__ import annotations

import json
from dataclasses import dataclass
from pathlib import Path


DEFAULT_CONFIG_DIR = Path("/submitty/config")


class ConfigError(Exception):
    """Raised when a required config file is missing or malformed."""


@dataclass
class SubmittyConfig:
    """Server connection details parsed from the Submitty config directory."""
    server_url: str
    token: str
    install_dir: str
    data_dir: str


def load_config(config_dir: Path = DEFAULT_CONFIG_DIR) -> SubmittyConfig:
    """Load configuration from the Submitty config directory."""
    submitty_json = config_dir / "submitty.json"
    admin_json = config_dir / "submitty_admin.json"

    for path in (submitty_json, admin_json):
        if not path.exists():
            raise ConfigError(f"Config file not found: {path}")

    try:
        with submitty_json.open() as f:
            submitty = json.load(f)
    except json.JSONDecodeError as e:
        raise ConfigError(f"Invalid JSON in {submitty_json}: {e}") from e

    try:
        with admin_json.open() as f:
            admin = json.load(f)
    except json.JSONDecodeError as e:
        raise ConfigError(f"Invalid JSON in {admin_json}: {e}") from e

    try:
        return SubmittyConfig(
            server_url=submitty["submission_url"].rstrip("/"),
            token=admin["token"],
            install_dir=submitty.get("submitty_install_dir", ""),
            data_dir=submitty.get("submitty_data_dir", ""),
        )
    except KeyError as e:
        raise ConfigError(f"Missing required config key: {e}") from e
