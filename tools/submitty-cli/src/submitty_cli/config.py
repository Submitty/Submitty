"""Config loader: token and server URL from user-level files or environment variables."""
from __future__ import annotations

import os
from dataclasses import dataclass
from pathlib import Path


_CONFIG_DIR = Path.home() / ".config" / "submitty"
TOKEN_FILE = _CONFIG_DIR / "token"
USER_FILE = _CONFIG_DIR / "user"
SERVER_FILE = _CONFIG_DIR / "server"

DEFAULT_SERVER = "http://localhost"


class ConfigError(Exception):
    """Raised when a required config value is missing."""


@dataclass
class SubmittyConfig:
    """Server connection details for the Submitty API."""

    server_url: str
    token: str


def load_server() -> str:
    """Return the server URL from the first available source.

    Resolution order:
    1. SUBMITTY_SERVER environment variable
    2. ~/.config/submitty/server (written by 'submitty auth login')
    3. http://localhost (default for server-local use)
    """
    if env_server := os.environ.get("SUBMITTY_SERVER"):
        return env_server.rstrip("/")
    if SERVER_FILE.exists():
        if server := SERVER_FILE.read_text(encoding="utf-8").strip():
            return server.rstrip("/")
    return DEFAULT_SERVER


def save_server(url: str) -> None:
    """Write the server URL to the user-level server file."""
    SERVER_FILE.parent.mkdir(parents=True, exist_ok=True)
    SERVER_FILE.write_text(url.rstrip("/"), encoding="utf-8")


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
