from typer.testing import CliRunner

from submitty_cli.cli import app

runner = CliRunner()


def test_app_help():
    result = runner.invoke(app, ["--help"])
    assert result.exit_code == 0
    assert "submitty" in result.output.lower()


def test_no_args_shows_help():
    result = runner.invoke(app, [])
    assert "auth" in result.output
    assert "course" in result.output


def test_auth_help():
    result = runner.invoke(app, ["auth", "--help"])
    assert result.exit_code == 0
    assert "token" in result.output
    assert "status" in result.output


def test_course_help():
    result = runner.invoke(app, ["course", "--help"])
    assert result.exit_code == 0
    assert "list" in result.output
    assert "create" in result.output
