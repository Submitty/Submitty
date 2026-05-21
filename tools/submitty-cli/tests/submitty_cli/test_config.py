import pytest

from submitty_cli.config import (
    ConfigError,
    DEFAULT_SERVER,
    delete_token,
    load_server,
    resolve_token,
    save_server,
    save_token,
)


# ---------------------------------------------------------------------------
# resolve_token
# ---------------------------------------------------------------------------

def test_resolve_token_from_env(monkeypatch):
    monkeypatch.setenv("SUBMITTY_TOKEN", "env-token")
    assert resolve_token() == "env-token"


def test_resolve_token_from_token_file(monkeypatch, tmp_path):
    monkeypatch.delenv("SUBMITTY_TOKEN", raising=False)
    token_file = tmp_path / "token"
    token_file.write_text("file-token", encoding="utf-8")
    monkeypatch.setattr("submitty_cli.config.TOKEN_FILE", token_file)
    assert resolve_token() == "file-token"


def test_resolve_token_no_source_raises(monkeypatch, tmp_path):
    monkeypatch.delenv("SUBMITTY_TOKEN", raising=False)
    monkeypatch.setattr("submitty_cli.config.TOKEN_FILE", tmp_path / "no-token")
    with pytest.raises(ConfigError, match="submitty auth login"):
        resolve_token()


def test_resolve_token_env_wins_over_file(monkeypatch, tmp_path):
    monkeypatch.setenv("SUBMITTY_TOKEN", "env-token")
    token_file = tmp_path / "token"
    token_file.write_text("file-token", encoding="utf-8")
    monkeypatch.setattr("submitty_cli.config.TOKEN_FILE", token_file)
    assert resolve_token() == "env-token"


# ---------------------------------------------------------------------------
# load_server
# ---------------------------------------------------------------------------

def test_load_server_from_env(monkeypatch):
    monkeypatch.setenv("SUBMITTY_SERVER", "https://env.example.com")
    assert load_server() == "https://env.example.com"


def test_load_server_strips_trailing_slash(monkeypatch):
    monkeypatch.setenv("SUBMITTY_SERVER", "https://env.example.com/")
    assert load_server() == "https://env.example.com"


def test_load_server_from_file(monkeypatch, tmp_path):
    monkeypatch.delenv("SUBMITTY_SERVER", raising=False)
    server_file = tmp_path / "server"
    server_file.write_text("https://file.example.com", encoding="utf-8")
    monkeypatch.setattr("submitty_cli.config.SERVER_FILE", server_file)
    assert load_server() == "https://file.example.com"


def test_load_server_defaults_to_localhost(monkeypatch, tmp_path):
    monkeypatch.delenv("SUBMITTY_SERVER", raising=False)
    monkeypatch.setattr("submitty_cli.config.SERVER_FILE", tmp_path / "no-server")
    assert load_server() == DEFAULT_SERVER


def test_load_server_env_wins_over_file(monkeypatch, tmp_path):
    monkeypatch.setenv("SUBMITTY_SERVER", "https://env.example.com")
    server_file = tmp_path / "server"
    server_file.write_text("https://file.example.com", encoding="utf-8")
    monkeypatch.setattr("submitty_cli.config.SERVER_FILE", server_file)
    assert load_server() == "https://env.example.com"


# ---------------------------------------------------------------------------
# save_token / delete_token / save_server
# ---------------------------------------------------------------------------

def test_save_and_delete_token(monkeypatch, tmp_path):
    token_file = tmp_path / "submitty" / "token"
    monkeypatch.setattr("submitty_cli.config.TOKEN_FILE", token_file)
    save_token("my-token")
    assert token_file.read_text(encoding="utf-8") == "my-token"
    delete_token()
    assert not token_file.exists()


def test_delete_token_missing_file_is_safe(monkeypatch, tmp_path):
    monkeypatch.setattr("submitty_cli.config.TOKEN_FILE", tmp_path / "nonexistent")
    delete_token()


def test_save_server_writes_file(monkeypatch, tmp_path):
    server_file = tmp_path / "server"
    monkeypatch.setattr("submitty_cli.config.SERVER_FILE", server_file)
    save_server("https://submitty.example.com/")
    assert server_file.read_text(encoding="utf-8") == "https://submitty.example.com"
