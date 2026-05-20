"""Root Typer application: registers command groups and global options."""
from __future__ import annotations

from pathlib import Path

import typer
from typing_extensions import Annotated

from submitty_cli.commands.auth import auth_app
from submitty_cli.commands.course import course_app
from submitty_cli.config import DEFAULT_CONFIG_DIR
from submitty_cli.output import OutputFormat
from submitty_cli.state import AppState


app = typer.Typer(
    name="submitty",
    help="Submitty command-line interface",
    no_args_is_help=True,
)
app.add_typer(auth_app, name="auth", help="Authentication commands")
app.add_typer(course_app, name="course", help="Course management commands")


@app.callback()
def main(
    ctx: typer.Context,
    config_dir: Annotated[
        Path,
        typer.Option("--config-dir", help="Path to Submitty config directory"),
    ] = DEFAULT_CONFIG_DIR,
    fmt: Annotated[
        OutputFormat,
        typer.Option("--format", "-f", help="Output format (table or json)"),
    ] = OutputFormat.TABLE,
) -> None:
    """Configure global options and build the shared AppState for subcommands."""
    # Allow tests to inject a pre-built AppState via ctx.obj; always apply CLI format
    if not isinstance(ctx.obj, AppState):
        ctx.obj = AppState(config_dir=config_dir, fmt=fmt)
    ctx.obj.fmt = fmt
