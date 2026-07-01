"""Config loader: token from environment variable or ~/.config/submitty/token."""
from __future__ import annotations

import os
from dataclasses import dataclass
from pathlib import Path


_CONFIG_DIR = Path.home() / ".config" / "submitty"
TOKEN_FILE = _CONFIG_DIR / "token"

DEFAULT_SERVER = "http://localhost"


class ConfigError(Exception):
    """Raised when a required config value is missing."""


@dataclass
class SubmittyConfig:
    """Server connection details for the Submitty API."""

    server_url: str
    token: str


def load_server() -> str:
    """Return the server URL from SUBMITTY_SERVER env var or default."""
    if env_server := os.environ.get("SUBMITTY_SERVER"):
        return env_server.rstrip("/")
    return DEFAULT_SERVER


def resolve_token() -> str:
    """Return an API token from the first available source.

    Resolution order:
    1. SUBMITTY_TOKEN environment variable
    2. ~/.config/submitty/token (written by 'submitty auth login')
    """
    if env_token := os.environ.get("SUBMITTY_TOKEN"):
        return env_token
    if TOKEN_FILE.exists():
        if token_content := TOKEN_FILE.read_text(encoding="utf-8").strip():
            return token_content
    raise ConfigError(
        "No API token found. Run 'submitty auth login <user>' or set SUBMITTY_TOKEN."
    )
