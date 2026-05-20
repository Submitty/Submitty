import json

import pytest

from submitty_cli.config import ConfigError, load_config


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


def test_load_valid_config(tmp_path):
    _write_config(tmp_path)
    config = load_config(tmp_path)
    assert config.server_url == "https://submitty.example.com"
    assert config.token == "test-token-abc123"
    assert config.install_dir == "/submitty"
    assert config.data_dir == "/var/local/submitty"


def test_trailing_slash_stripped(tmp_path):
    _write_config(
        tmp_path,
        submitty_data={
            "submission_url": "https://submitty.example.com/",
            "submitty_install_dir": "/submitty",
            "submitty_data_dir": "/var/local/submitty",
        },
    )
    config = load_config(tmp_path)
    assert not config.server_url.endswith("/")


def test_missing_submitty_json(tmp_path):
    (tmp_path / "submitty_admin.json").write_text(json.dumps({"token": "x"}))
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


def test_missing_submission_url_key(tmp_path):
    _write_config(
        tmp_path,
        submitty_data={
            "submitty_install_dir": "/submitty",
            "submitty_data_dir": "/var/local/submitty",
        },
    )
    with pytest.raises(ConfigError, match="submission_url"):
        load_config(tmp_path)


def test_missing_token_key(tmp_path):
    _write_config(tmp_path, admin_data={})
    with pytest.raises(ConfigError, match="token"):
        load_config(tmp_path)


def test_invalid_json_in_submitty(tmp_path):
    (tmp_path / "submitty.json").write_text("not valid json")
    (tmp_path / "submitty_admin.json").write_text(json.dumps({"token": "x"}))
    with pytest.raises(ConfigError, match="Invalid JSON"):
        load_config(tmp_path)
