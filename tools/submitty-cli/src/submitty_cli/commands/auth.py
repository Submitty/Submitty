"""Authentication commands: token, status, login, logout."""
from __future__ import annotations

import typer
from typing_extensions import Annotated

from submitty_cli.client import AuthError
from submitty_cli.output import print_error, print_success
from submitty_cli.state import AppState

auth_app = typer.Typer(no_args_is_help=True)


@auth_app.command("token")
def token(ctx: typer.Context) -> None:
    """Print the current admin API token from config."""
    state: AppState = ctx.obj
    typer.echo(state.config.token)


@auth_app.command("status")
def status(ctx: typer.Context) -> None:
    """Validate the current token against the server."""
    state: AppState = ctx.obj
    try:
        result = state.client.get("/api/token")
        user = result.get("data", {}).get("user_id", "unknown")
        print_success(f"Authenticated as {user} at {state.config.server_url}")
    except AuthError as exc:
        print_error("Token is invalid or expired")
        raise typer.Exit(1) from exc


@auth_app.command("login")
def login(
    ctx: typer.Context,
    user_id: Annotated[str, typer.Argument(help="Submitty user ID")],
) -> None:
    """Authenticate with a user account and print the resulting token."""
    state: AppState = ctx.obj
    password = typer.prompt("Password", hide_input=True)
    try:
        result = state.client.post(
            "/api/token",
            json={"user_id": user_id, "password": password},
        )
        typer.echo(result.get("data", {}).get("token", ""))
    except AuthError as exc:
        print_error("Invalid credentials")
        raise typer.Exit(1) from exc


@auth_app.command("logout")
def logout(ctx: typer.Context) -> None:
    """Invalidate the current admin API token."""
    state: AppState = ctx.obj
    state.client.post("/api/token/invalidate")
    print_success("Token invalidated")
