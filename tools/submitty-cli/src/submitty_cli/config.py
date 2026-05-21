"""Config loader: reads /usr/local/submitty/config/submitty.json and submitty_admin.json."""
from __future__ import annotations

import json
import os
from dataclasses import dataclass
from pathlib import Path


DEFAULT_CONFIG_DIR = Path("/usr/local/submitty/config")
TOKEN_FILE = Path.home() / ".config" / "submitty" / "token"
USER_FILE = Path.home() / ".config" / "submitty" / "user"


class ConfigError(Exception):
    """Raised when a required config file is missing or malformed."""


@dataclass
class SubmittyConfig:
    """Server connection details parsed from the Submitty config directory."""

    server_url: str
    token: str
    install_dir: str
    data_dir: str


def resolve_token(admin: dict) -> str:
    """Return an API token from the first available source.

    Resolution order:
    1. SUBMITTY_TOKEN environment variable
    2. ~/.config/submitty/token (written by 'submitty auth login')
    3. 'token' key in submitty_admin.json (server-local installs)
    """
    if env_token := os.environ.get("SUBMITTY_TOKEN"):
        return env_token
    if TOKEN_FILE.exists():
        if token_content := TOKEN_FILE.read_text(encoding="utf-8").strip():
            return token_content
    if server_token := admin.get("token"):
        return server_token
    raise ConfigError(
        "No API token found. Run 'submitty auth login <user>' or set SUBMITTY_TOKEN."
    )


def save_token(token: str) -> None:
    """Write a token to the user-level token file."""
    TOKEN_FILE.parent.mkdir(parents=True, exist_ok=True)
    TOKEN_FILE.write_text(token, encoding="utf-8")


def save_user(user_id: str) -> None:
    """Write the authenticated user ID alongside the token."""
    USER_FILE.parent.mkdir(parents=True, exist_ok=True)
    USER_FILE.write_text(user_id, encoding="utf-8")


def load_user() -> str:
    """Return the cached user ID, or empty string if not logged in."""
    if USER_FILE.exists():
        return USER_FILE.read_text(encoding="utf-8").strip()
    return ""


def delete_token() -> None:
    """Remove the cached token and user files, if present."""
    TOKEN_FILE.unlink(missing_ok=True)
    USER_FILE.unlink(missing_ok=True)


def load_server_url(config_dir: Path = DEFAULT_CONFIG_DIR) -> str:
    """Return the server URL from submitty.json without requiring a token."""
    submitty_json = config_dir / "submitty.json"
    if not submitty_json.exists():
        raise ConfigError(f"Config file not found: {submitty_json}")
    try:
        with submitty_json.open() as f:
            submitty = json.load(f)
    except json.JSONDecodeError as e:
        raise ConfigError(f"Invalid JSON in {submitty_json}: {e}") from e
    try:
        return submitty["submission_url"].rstrip("/")
    except KeyError as e:
        raise ConfigError(f"Missing required config key: {e}") from e


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
            token=resolve_token(admin),
            install_dir=submitty.get("submitty_install_dir", ""),
            data_dir=submitty.get("submitty_data_dir", ""),
        )
    except KeyError as e:
        raise ConfigError(f"Missing required config key: {e}") from e
