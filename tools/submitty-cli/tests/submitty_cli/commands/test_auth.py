from submitty_cli.cli import app
from submitty_cli.client import AuthError


def test_token_prints_token(runner, mock_state):
    result = runner.invoke(app, ["auth", "token"], obj=mock_state)
    assert result.exit_code == 0
    assert mock_state.config.token in result.output


def test_status_success(runner, mock_state):
    mock_state.client.get.return_value = {"status": "success", "data": {}}
    result = runner.invoke(app, ["auth", "status"], obj=mock_state)
    assert result.exit_code == 0
    assert mock_state.config.server_url in result.output


def test_status_auth_failure_exits_nonzero(runner, mock_state):
    mock_state.client.get.side_effect = AuthError("Authentication failed", 401)
    result = runner.invoke(app, ["auth", "status"], obj=mock_state)
    assert result.exit_code == 1
