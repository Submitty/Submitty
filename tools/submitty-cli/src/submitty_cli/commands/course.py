"""Course management commands: list, create, config get/set."""
from __future__ import annotations

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
    data = result.get("data", {})
    courses = data.get("unarchived_courses", []) + data.get("archived_courses", [])

    if state.fmt == OutputFormat.JSON:
        print_json(courses)
    else:
        rows = [
            {
                "term": c.get("display_semester") or c.get("semester", ""),
                "key": c.get("semester", ""),
                "course": c.get("title", ""),
                "name": c.get("display_name", ""),
            }
            for c in courses
        ]
        print_table(rows, columns=["term", "key", "course", "name"])


@course_app.command("create")
def course_create(
    ctx: typer.Context,
    term: Annotated[str, typer.Argument(help="Term key (e.g. winter26)")],
    course: Annotated[str, typer.Argument(help="Course identifier (e.g. cptr142)")],
    instructor: Annotated[str, typer.Option("--instructor", help="Head instructor user ID")],
    group: Annotated[str, typer.Option("--group", help="Unix group for the course")] = "",
) -> None:
    """Create a new course."""
    state: AppState = ctx.obj
    try:
        state.client.post(
            "/api/courses",
            data={
                "course_semester": term,
                "course_title": course,
                "head_instructor": instructor,
                "group_name": group or f"{term}_{course}",
            },
        )
        typer.echo(f"Course {term}/{course} created")
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
    semester: Annotated[str, typer.Argument(help="Term key (e.g. winter26)")],
    course: Annotated[str, typer.Argument(help="Course identifier (e.g. cptr142)")],
    name: Annotated[str, typer.Argument(help="Config key to update (e.g. course_name)")],
    value: Annotated[str, typer.Argument(help="New value")],
) -> None:
    """Update a single course configuration value."""
    state: AppState = ctx.obj
    try:
        state.client.post(
            f"/api/courses/{semester}/{course}/config",
            data={"name": name, "entry": value},
        )
        typer.echo(f"Set {name} for {semester}/{course}")
    except APIError as e:
        print_error(str(e))
        raise typer.Exit(1)
