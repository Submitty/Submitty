"""Output helpers: table and JSON formatters, error/success printers."""
from __future__ import annotations

import json
from enum import Enum
from typing import Any, List

import typer
from rich.console import Console
from rich.table import Table


console = Console()
err_console = Console(stderr=True, style="bold red")


class OutputFormat(str, Enum):
    """Supported CLI output formats."""

    TABLE = "table"
    JSON = "json"


def print_json(data: Any) -> None:
    """Print data as indented JSON."""
    typer.echo(json.dumps(data, indent=2))


def print_table(rows: List[dict], columns: List[str]) -> None:
    """Print rows as a rich table with the given column headers."""
    table = Table(*columns, show_header=True, header_style="bold cyan")
    for row in rows:
        table.add_row(*[str(row.get(col, "")) for col in columns])
    console.print(table)


def print_error(message: str) -> None:
    """Print an error message to stderr in red."""
    err_console.print(f"Error: {message}")


def print_success(message: str) -> None:
    """Print a success message in green."""
    console.print(f"[green]{message}[/green]")
