# submitty-cli

A unified command-line interface for Submitty system administration. This tool is the long-term replacement for the collection of individual scripts in [`sbin/`](../../sbin/), consolidating them into a single, fully-tested CLI that operates through the [Submitty REST API](https://submitty.org/developer/api).

## Motivation

The `sbin/` directory contains dozens of standalone Python and shell scripts (`adduser.py`, `create_course.sh`, `generate_grade_summaries.py`, etc.) that each solve one problem in isolation. Over time this creates:

- **No shared test coverage** — individual scripts are difficult to unit test without a live server
- **Duplicated config loading** — every script re-reads `/submitty/config/*.json` in its own way
- **Inconsistent interfaces** — flags, output formats, and error codes vary between scripts
- **Fragmented documentation** — behavior lives only in the script itself

`submitty` replaces these scripts one at a time with subcommands that share a common config loader, HTTP client, output formatter, and test harness. Existing `sbin/` scripts are not removed until their replacement command is stable.

## Installation

```bash
# From the tools/submitty-cli/ directory
pip install -e ".[dev]"
```

This installs the `submitty` entry point and all development dependencies.

## Configuration

The CLI reads server details from the standard Submitty config directory (default: `/submitty/config`):

| File | Fields used |
|---|---|
| `submitty.json` | `submission_url` (base API URL), `submitty_install_dir`, `submitty_data_dir` |
| `submitty_admin.json` | `token` (admin API token) |

Override the config directory for testing or non-standard installs:

```bash
submitty --config-dir /path/to/config auth status
```

## Usage

```
submitty [--config-dir PATH] [--format {table,json}] <command> <subcommand> [args]
```

### auth — Authentication

```bash
submitty auth token          # Print the admin API token from config
submitty auth status         # Validate the token against the server
submitty auth login <user>   # Authenticate as a user, print the resulting token
submitty auth logout         # Invalidate the current admin token
```

### course — Course management

```bash
submitty course list                              # List all courses
submitty course create s25 csci1200               # Create a course
submitty course create s25 csci1200 --group mygrp # Create with a custom Unix group
submitty course config get s25 csci1200           # Get course configuration (JSON)
submitty course config set s25 csci1200 '{"key": "value"}'  # Update course config
```

### Output formats

Every command supports `--format json` for scripting:

```bash
submitty --format json course list | jq '.[].semester'
```

## Development

### Project layout

```
src/submitty_cli/
  config.py        Loads /submitty/config/*.json → SubmittyConfig
  client.py        httpx-based API client; raises AuthError / NotFoundError / APIError
  state.py         AppState — lazy config + client, injected via Typer context
  output.py        OutputFormat enum, print_table / print_json / print_error
  cli.py           Root Typer app; registers command groups
  commands/
    auth.py        auth token / status / login / logout
    course.py      course list / create / config get / config set

tests/submitty_cli/          mirrors src/submitty_cli/ (no __init__.py)
  conftest.py      Shared fixtures: sample_config, mock_client, mock_state, runner
  test_cli.py
  test_config.py
  test_client.py
  commands/
    test_auth.py
    test_course.py
```

### Running tests

```bash
pytest                    # all tests
pytest -v                 # verbose
pytest --cov              # with coverage report
pytest tests/submitty_cli/commands/test_auth.py  # single file
```

### Adding a new command

1. Create `src/submitty_cli/commands/<noun>.py` with a `<noun>_app = typer.Typer()`.
2. Register it in `src/submitty_cli/cli.py`:
   ```python
   from submitty_cli.commands.<noun> import <noun>_app
   app.add_typer(<noun>_app, name="<noun>", help="...")
   ```
3. Create the matching test file at `tests/submitty_cli/commands/test_<noun>.py`.
4. Access config and the HTTP client through `ctx.obj` (an `AppState`):
   ```python
   @<noun>_app.command("list")
   def noun_list(ctx: typer.Context) -> None:
       state: AppState = ctx.obj
       result = state.client.get("/api/...")
   ```

### Testing commands in isolation

Commands are tested without a live server by injecting a pre-built `AppState` with a `MagicMock` client:

```python
def test_my_command(runner, mock_state):
    mock_state.client.get.return_value = {"status": "success", "data": [...]}
    result = runner.invoke(app, ["noun", "list"], obj=mock_state)
    assert result.exit_code == 0
```

The `runner`, `mock_state`, `mock_client`, and `sample_config` fixtures are defined in `tests/submitty_cli/conftest.py` and available to all test files automatically.

## sbin migration status

| sbin script | Replacement command | Status |
|---|---|---|
| `api_token_generate.php` | `submitty auth token` | Done |
| `adduser.py` | `submitty user add` | Planned |
| `adduser_course.py` | `submitty user enroll` | Planned |
| `create_course.sh` | `submitty course create` | In progress |
| `generate_grade_summaries.py` | `submitty report summary` | Planned |
| `auto_rainbow_grades.py` | `submitty report rainbow build` | Planned |
| `restart_shipper_and_all_workers.py` | `submitty worker restart` | Planned |
| `killall_shippers_and_workers.sh` | `submitty worker stop` | Planned |
| `update_worker_sysinfo.sh` | `submitty worker sysinfo` | Planned |
| `docker_cleanup.sh` | `submitty docker cleanup` | Planned |
| `delete_expired_sessions.py` | `submitty admin session cleanup` | Planned |
| `check_everything.py` | `submitty admin check` | Planned |
| `anonymize.py` | `submitty admin anonymize` | Planned |
| `send_email.py` | internal daemon (no CLI replacement) | — |
