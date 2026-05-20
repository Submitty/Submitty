"""Course management commands: list, create, config get/set."""
from __future__ import annotations

import json

import typer
from typing_extensions import Annotated

from submitty_cli.client import APIError
from submitty_cli.output import OutputFormat, print_error, print_json, print_table
from submitty_cli.state import AppState

course_app = typer.Typer(no_args_is_help=True)
course_config_app = typer.Typer(no_args_is_help=True)
course_app.add_typer(course_config_app, name="config", help="Course configuration")


@course_app.command("list")
def course_list(ctx: typer.Context) -> None:
    """List all courses."""
    state: AppState = ctx.obj
    result = state.client.get("/api/courses")
    courses = result.get("data", {}).get("courses", [])

    if state.fmt == OutputFormat.JSON:
        print_json(courses)
    else:
        rows = [
            {
                "semester": c.get("semester", ""),
                "course": c.get("title", ""),
                "name": c.get("display_name", ""),
            }
            for c in courses
        ]
        print_table(rows, columns=["semester", "course", "name"])


@course_app.command("create")
def course_create(
    ctx: typer.Context,
    semester: Annotated[str, typer.Argument(help="Semester identifier (e.g. s25)")],
    course: Annotated[str, typer.Argument(help="Course identifier (e.g. csci1200)")],
    group: Annotated[str, typer.Option("--group", help="Unix group for the course")] = "",
    section: Annotated[str, typer.Option("--section", help="Registration section")] = "",
) -> None:
    """Create a new course."""
    state: AppState = ctx.obj
    try:
        state.client.post(
            "/api/courses",
            json={
                "semester": semester,
                "course": course,
                "group": group or f"{semester}_{course}",
                "section": section,
            },
        )
        typer.echo(f"Course {semester}/{course} created")
    except APIError as e:
        print_error(str(e))
        raise typer.Exit(1)


@course_config_app.command("get")
def config_get(
    ctx: typer.Context,
    semester: Annotated[str, typer.Argument(help="Semester identifier")],
    course: Annotated[str, typer.Argument(help="Course identifier")],
) -> None:
    """Get course configuration."""
    state: AppState = ctx.obj
    try:
        result = state.client.get(f"/api/courses/{semester}/{course}/config")
        print_json(result.get("data", {}))
    except APIError as e:
        print_error(str(e))
        raise typer.Exit(1)


@course_config_app.command("set")
def config_set(
    ctx: typer.Context,
    semester: Annotated[str, typer.Argument(help="Semester identifier")],
    course: Annotated[str, typer.Argument(help="Course identifier")],
    data: Annotated[str, typer.Argument(help="Configuration values as a JSON string")],
) -> None:
    """Update course configuration."""
    state: AppState = ctx.obj
    try:
        payload = json.loads(data)
    except json.JSONDecodeError as e:
        print_error(f"Invalid JSON: {e}")
        raise typer.Exit(1)

    try:
        state.client.post(f"/api/courses/{semester}/{course}/config", json=payload)
        typer.echo(f"Config updated for {semester}/{course}")
    except APIError as e:
        print_error(str(e))
        raise typer.Exit(1)
