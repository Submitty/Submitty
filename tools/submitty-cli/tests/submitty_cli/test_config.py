import json
import os

import pytest

from submitty_cli.config import ConfigError, load_config, resolve_token, save_token, delete_token


def _write_config(tmp_path, submitty_data=None, admin_data=None):
    if submitty_data is None:
        submitty_data = {
            "submission_url": "https://submitty.example.com",
            "submitty_install_dir": "/submitty",
            "submitty_data_dir": "/var/local/submitty",
        }
    if admin_data is None:
        admin_data = {"token": "test-token-abc123"}

    (tmp_path / "submitty.json").write_text(json.dumps(submitty_data))
    (tmp_path / "submitty_admin.json").write_text(json.dumps(admin_data))


# ---------------------------------------------------------------------------
# resolve_token unit tests
# ---------------------------------------------------------------------------

def test_resolve_token_from_env(monkeypatch, tmp_path):
    """SUBMITTY_TOKEN env var takes priority over everything else."""
    monkeypatch.setenv("SUBMITTY_TOKEN", "env-token")
    assert resolve_token({"token": "config-token"}) == "env-token"


def test_resolve_token_from_token_file(monkeypatch, tmp_path):
    """Cached token file is used when env var is absent."""
    monkeypatch.delenv("SUBMITTY_TOKEN", raising=False)
    token_file = tmp_path / "token"
    token_file.write_text("file-token", encoding="utf-8")
    monkeypatch.setattr("submitty_cli.config.TOKEN_FILE", token_file)
    assert resolve_token({}) == "file-token"


def test_resolve_token_from_admin_json(monkeypatch, tmp_path):
    """Falls back to submitty_admin.json 'token' key."""
    monkeypatch.delenv("SUBMITTY_TOKEN", raising=False)
    monkeypatch.setattr("submitty_cli.config.TOKEN_FILE", tmp_path / "no-token")
    assert resolve_token({"token": "config-token"}) == "config-token"


def test_resolve_token_no_source_raises(monkeypatch, tmp_path):
    """Raises ConfigError with actionable message when no token is found."""
    monkeypatch.delenv("SUBMITTY_TOKEN", raising=False)
    monkeypatch.setattr("submitty_cli.config.TOKEN_FILE", tmp_path / "no-token")
    with pytest.raises(ConfigError, match="submitty auth login"):
        resolve_token({})


def test_resolve_token_env_wins_over_file(monkeypatch, tmp_path):
    """Env var takes priority over cached token file."""
    monkeypatch.setenv("SUBMITTY_TOKEN", "env-token")
    token_file = tmp_path / "token"
    token_file.write_text("file-token", encoding="utf-8")
    monkeypatch.setattr("submitty_cli.config.TOKEN_FILE", token_file)
    assert resolve_token({}) == "env-token"


# ---------------------------------------------------------------------------
# save_token / delete_token
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
    delete_token()  # should not raise


# ---------------------------------------------------------------------------
# load_config integration tests
# ---------------------------------------------------------------------------

def test_load_valid_config(monkeypatch, tmp_path):
    monkeypatch.setenv("SUBMITTY_TOKEN", "test-token-abc123")
    _write_config(tmp_path, admin_data={})
    config = load_config(tmp_path)
    assert config.server_url == "https://submitty.example.com"
    assert config.token == "test-token-abc123"
    assert config.install_dir == "/submitty"
    assert config.data_dir == "/var/local/submitty"


def test_load_config_token_from_admin_json(monkeypatch, tmp_path):
    monkeypatch.delenv("SUBMITTY_TOKEN", raising=False)
    monkeypatch.setattr("submitty_cli.config.TOKEN_FILE", tmp_path / "no-token")
    _write_config(tmp_path)
    config = load_config(tmp_path)
    assert config.token == "test-token-abc123"


def test_load_config_no_token_raises(monkeypatch, tmp_path):
    monkeypatch.delenv("SUBMITTY_TOKEN", raising=False)
    monkeypatch.setattr("submitty_cli.config.TOKEN_FILE", tmp_path / "no-token")
    _write_config(tmp_path, admin_data={"submitty_admin_username": "admin"})
    with pytest.raises(ConfigError, match="submitty auth login"):
        load_config(tmp_path)


def test_trailing_slash_stripped(monkeypatch, tmp_path):
    monkeypatch.setenv("SUBMITTY_TOKEN", "tok")
    _write_config(
        tmp_path,
        submitty_data={
            "submission_url": "https://submitty.example.com/",
            "submitty_install_dir": "/submitty",
            "submitty_data_dir": "/var/local/submitty",
        },
        admin_data={},
    )
    config = load_config(tmp_path)
    assert not config.server_url.endswith("/")


def test_missing_submitty_json(tmp_path):
    (tmp_path / "submitty_admin.json").write_text(json.dumps({}))
    with pytest.raises(ConfigError, match="submitty.json"):
        load_config(tmp_path)


def test_missing_admin_json(tmp_path):
    (tmp_path / "submitty.json").write_text(
        json.dumps({
            "submission_url": "https://x.com",
            "submitty_install_dir": "/x",
            "submitty_data_dir": "/x",
        })
    )
    with pytest.raises(ConfigError, match="submitty_admin.json"):
        load_config(tmp_path)


def test_missing_submission_url_key(monkeypatch, tmp_path):
    monkeypatch.setenv("SUBMITTY_TOKEN", "tok")
    _write_config(
        tmp_path,
        submitty_data={
            "submitty_install_dir": "/submitty",
            "submitty_data_dir": "/var/local/submitty",
        },
        admin_data={},
    )
    with pytest.raises(ConfigError, match="submission_url"):
        load_config(tmp_path)


def test_invalid_json_in_submitty(tmp_path):
    (tmp_path / "submitty.json").write_text("not valid json")
    (tmp_path / "submitty_admin.json").write_text(json.dumps({}))
    with pytest.raises(ConfigError, match="Invalid JSON"):
        load_config(tmp_path)
