from unittest.mock import patch

import httpx
import respx

from submitty_cli.cli import app
from submitty_cli.client import AuthError

LOGIN_URL = "https://submitty.example.com/api/token"


def test_token_prints_token(runner, mock_state):
    result = runner.invoke(app, ["auth", "token"], obj=mock_state)
    assert result.exit_code == 0
    assert mock_state.config.token in result.output


def test_status_success(runner, mock_state):
    mock_state.client.get.return_value = {"status": "success", "data": {}}
    with patch("submitty_cli.commands.auth.load_user", return_value="instructor01"):
        result = runner.invoke(app, ["auth", "status"], obj=mock_state)
    assert result.exit_code == 0
    assert "instructor01" in result.output
    assert mock_state.config.server_url in result.output


def test_status_unknown_user_when_not_logged_in(runner, mock_state):
    mock_state.client.get.return_value = {"status": "success", "data": {}}
    with patch("submitty_cli.commands.auth.load_user", return_value=""):
        result = runner.invoke(app, ["auth", "status"], obj=mock_state)
    assert result.exit_code == 0
    assert "unknown" in result.output


def test_status_auth_failure_exits_nonzero(runner, mock_state):
    mock_state.client.get.side_effect = AuthError("Authentication failed", 401)
    result = runner.invoke(app, ["auth", "status"], obj=mock_state)
    assert result.exit_code == 1


def test_logout_invalidates_and_removes_token(runner, mock_state):
    mock_state.client.post.return_value = {"status": "success"}
    with patch("submitty_cli.commands.auth.delete_token") as mock_delete:
        result = runner.invoke(app, ["auth", "logout"], obj=mock_state)
    assert result.exit_code == 0
    mock_state.client.post.assert_called_once_with("/api/token/invalidate")
    mock_delete.assert_called_once()


@respx.mock
def test_login_saves_token_and_user(runner, mock_state):
    """login uses an unauthenticated httpx call — mock at the httpx level."""
    respx.post(LOGIN_URL).mock(
        return_value=httpx.Response(200, json={"data": {"token": "new-token-xyz"}})
    )
    with (
        patch("submitty_cli.commands.auth.save_token") as mock_save_token,
        patch("submitty_cli.commands.auth.save_user") as mock_save_user,
        patch("submitty_cli.commands.auth.save_server"),
    ):
        result = runner.invoke(
            app,
            ["auth", "login", "--server", "https://submitty.example.com", "instructor01"],
            input="secret\n",
            obj=mock_state,
        )
    assert result.exit_code == 0
    mock_save_token.assert_called_once_with("new-token-xyz")
    mock_save_user.assert_called_once_with("instructor01")


@respx.mock
def test_login_saves_server_url(runner, mock_state):
    respx.post(LOGIN_URL).mock(
        return_value=httpx.Response(200, json={"data": {"token": "new-token-xyz"}})
    )
    with (
        patch("submitty_cli.commands.auth.save_token"),
        patch("submitty_cli.commands.auth.save_user"),
        patch("submitty_cli.commands.auth.save_server") as mock_save_server,
    ):
        runner.invoke(
            app,
            ["auth", "login", "--server", "https://submitty.example.com/", "instructor01"],
            input="secret\n",
            obj=mock_state,
        )
    mock_save_server.assert_called_once_with("https://submitty.example.com")


@respx.mock
def test_login_bad_credentials_exits_nonzero(runner, mock_state):
    respx.post(LOGIN_URL).mock(return_value=httpx.Response(401))
    result = runner.invoke(
        app,
        ["auth", "login", "--server", "https://submitty.example.com", "instructor01"],
        input="wrong\n",
        obj=mock_state,
    )
    assert result.exit_code == 1


@respx.mock
def test_login_status_fail_exits_nonzero(runner, mock_state):
    """Submitty returns HTTP 200 with status=fail for bad credentials."""
    respx.post(LOGIN_URL).mock(
        return_value=httpx.Response(
            200, json={"status": "fail", "message": "Invalid credentials"}
        )
    )
    result = runner.invoke(
        app,
        ["auth", "login", "--server", "https://submitty.example.com", "instructor01"],
        input="wrong\n",
        obj=mock_state,
    )
    assert result.exit_code == 1


@respx.mock
def test_login_server_error_exits_nonzero(runner, mock_state):
    respx.post(LOGIN_URL).mock(return_value=httpx.Response(500))
    result = runner.invoke(
        app,
        ["auth", "login", "--server", "https://submitty.example.com", "instructor01"],
        input="secret\n",
        obj=mock_state,
    )
    assert result.exit_code == 1
