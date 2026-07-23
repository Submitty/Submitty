"""Authentication commands: token, status."""
from __future__ import annotations

import typer

from submitty_cli.client import AuthError
from submitty_cli.output import print_error, print_success
from submitty_cli.state import AppState

auth_app = typer.Typer(no_args_is_help=True)


@auth_app.command("token")
def token(ctx: typer.Context) -> None:
    """Print the active API token (env var or cached file)."""
    state: AppState = ctx.obj
    typer.echo(state.config.token)


@auth_app.command("status")
def status(ctx: typer.Context) -> None:
    """Validate the current token against the server."""
    state: AppState = ctx.obj
    try:
        state.client.get("/api/courses")
        print_success(f"Authenticated at {state.config.server_url}")
    except AuthError as exc:
        print_error("Token is invalid or expired")
        raise typer.Exit(1) from exc
