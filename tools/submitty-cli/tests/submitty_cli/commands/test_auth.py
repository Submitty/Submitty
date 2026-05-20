from submitty_cli.cli import app
from submitty_cli.client import AuthError


def test_token_prints_token(runner, mock_state):
    result = runner.invoke(app, ["auth", "token"], obj=mock_state)
    assert result.exit_code == 0
    assert mock_state.config.token in result.output


def test_status_success(runner, mock_state):
    mock_state.client.get.return_value = {
        "status": "success",
        "data": {"user_id": "submitty_admin"},
    }
    result = runner.invoke(app, ["auth", "status"], obj=mock_state)
    assert result.exit_code == 0
    assert "submitty_admin" in result.output
    assert mock_state.config.server_url in result.output


def test_status_auth_failure_exits_nonzero(runner, mock_state):
    mock_state.client.get.side_effect = AuthError("Authentication failed", 401)
    result = runner.invoke(app, ["auth", "status"], obj=mock_state)
    assert result.exit_code == 1


def test_logout_calls_invalidate_endpoint(runner, mock_state):
    mock_state.client.post.return_value = {"status": "success"}
    result = runner.invoke(app, ["auth", "logout"], obj=mock_state)
    assert result.exit_code == 0
    mock_state.client.post.assert_called_once_with("/api/token/invalidate")


def test_login_success_prints_token(runner, mock_state):
    mock_state.client.post.return_value = {
        "status": "success",
        "data": {"token": "new-token-xyz"},
    }
    result = runner.invoke(app, ["auth", "login", "instructor01"], input="secret\n", obj=mock_state)
    assert result.exit_code == 0
    assert "new-token-xyz" in result.output


def test_login_bad_credentials_exits_nonzero(runner, mock_state):
    mock_state.client.post.side_effect = AuthError("Invalid credentials", 401)
    result = runner.invoke(app, ["auth", "login", "instructor01"], input="wrong\n", obj=mock_state)
    assert result.exit_code == 1
