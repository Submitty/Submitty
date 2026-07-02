import pytest

from submitty_cli.config import ConfigError, DEFAULT_SERVER, resolve_token, load_server


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


def test_load_server_from_env(monkeypatch):
    monkeypatch.setenv("SUBMITTY_SERVER", "https://submitty.example.com")
    assert load_server() == "https://submitty.example.com"


def test_load_server_strips_trailing_slash(monkeypatch):
    monkeypatch.setenv("SUBMITTY_SERVER", "https://submitty.example.com/")
    assert load_server() == "https://submitty.example.com"


def test_load_server_defaults_to_localhost(monkeypatch):
    monkeypatch.delenv("SUBMITTY_SERVER", raising=False)
    assert load_server() == DEFAULT_SERVER
