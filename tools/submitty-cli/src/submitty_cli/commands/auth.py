"""Authentication commands: token, status, login, logout."""
from __future__ import annotations

import httpx
import typer
from typing_extensions import Annotated

from submitty_cli.client import AuthError
from submitty_cli.config import (
    DEFAULT_SERVER, TOKEN_FILE, delete_token, load_user, save_server, save_token, save_user,
)
from submitty_cli.output import print_error, print_success
from submitty_cli.state import AppState

auth_app = typer.Typer(no_args_is_help=True)


@auth_app.command("token")
def token(ctx: typer.Context) -> None:
    """Print the active API token (env var, cached file, or config)."""
    state: AppState = ctx.obj
    typer.echo(state.config.token)


@auth_app.command("status")
def status(ctx: typer.Context) -> None:
    """Validate the current token against the server."""
    state: AppState = ctx.obj
    try:
        state.client.get("/api/courses")
        user = load_user() or "unknown"
        print_success(f"Authenticated as {user} at {state.config.server_url}")
    except AuthError as exc:
        print_error("Token is invalid or expired")
        raise typer.Exit(1) from exc


@auth_app.command("login")
def login(
    user_id: Annotated[str, typer.Argument(help="Submitty user ID")],
    server: Annotated[
        str,
        typer.Option("--server", help="Submitty server URL (saved for future commands)"),
    ] = DEFAULT_SERVER,
) -> None:
    """Authenticate with username and password; cache the server URL and token."""
    server_url = server.rstrip("/")
    password = typer.prompt("Password", hide_input=True)
    try:
        response = httpx.post(
            f"{server_url}/api/token",
            data={"user_id": user_id, "password": password},
            timeout=30.0,
        )
    except httpx.RequestError as exc:
        print_error(f"Could not reach server: {exc}")
        raise typer.Exit(1) from exc

    if response.status_code == 401:
        print_error("Invalid credentials")
        raise typer.Exit(1)

    if response.is_error:
        print_error(f"Login failed ({response.status_code})")
        raise typer.Exit(1)

    try:
        body = response.json()
    except Exception:
        print_error("Login failed: server returned non-JSON response")
        raise typer.Exit(1)

    if body.get("status") == "fail":
        print_error(body.get("message", "Invalid credentials"))
        raise typer.Exit(1)

    new_token = body.get("data", {}).get("token", "")
    if not new_token:
        print_error(f"Login succeeded but no token in response")
        raise typer.Exit(1)

    save_server(server_url)
    save_token(new_token)
    save_user(user_id)
    print_success(f"Logged in as {user_id} at {server_url}. Token saved to {TOKEN_FILE}")


@auth_app.command("logout")
def logout(ctx: typer.Context) -> None:
    """Invalidate the current token on the server and remove the cached token file."""
    state: AppState = ctx.obj
    state.client.post("/api/token/invalidate")
    delete_token()
    print_success("Logged out and token removed")
