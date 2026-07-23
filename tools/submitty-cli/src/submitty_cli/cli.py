"""Root Typer application: registers command groups and global options."""
from __future__ import annotations

import typer
from typing_extensions import Annotated

from submitty_cli.client import APIError
from submitty_cli.commands.auth import auth_app
from submitty_cli.config import ConfigError
from submitty_cli.output import OutputFormat, print_error
from submitty_cli.state import AppState


app = typer.Typer(
    name="submitty",
    help="Submitty command-line interface",
    no_args_is_help=True,
)
app.add_typer(auth_app, name="auth", help="Authentication commands")


@app.callback()
def callback(
    ctx: typer.Context,
    fmt: Annotated[
        OutputFormat,
        typer.Option("--format", "-f", help="Output format (table or json)"),
    ] = OutputFormat.TABLE,
) -> None:
    """Configure global options and build the shared AppState for subcommands."""
    if not isinstance(ctx.obj, AppState):
        ctx.obj = AppState(fmt=fmt)
    ctx.obj.fmt = fmt


def main() -> None:
    """Entry point that wraps app() with clean error handling for config/API errors."""
    try:
        app()
    except ConfigError as exc:
        print_error(str(exc))
        raise SystemExit(1) from exc
    except APIError as exc:
        print_error(str(exc))
        raise SystemExit(1) from exc
