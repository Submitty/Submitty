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

### With uv (recommended)

[uv](https://docs.astral.sh/uv/) is the fastest way to get started — no manual venv management needed.

```bash
# Install uv (once, system-wide)
curl -LsSf https://astral.sh/uv/install.sh | sh

# From the tools/submitty-cli/ directory:

# Run tests or commands without activating a venv
uv run pytest
uv run submitty auth status

# Install as a persistent global tool (like pipx)
uv tool install .
submitty --help
```

### With pip

```bash
# From the tools/submitty-cli/ directory
pip install -e ".[dev]"
```

## Configuration

The CLI requires no config files. All connection details are stored in `~/.config/submitty/` after the first login:

| File | Contents | Set by |
|---|---|---|
| `~/.config/submitty/server` | Server URL | `submitty auth login --server <url>` |
| `~/.config/submitty/token` | API token | `submitty auth login` |
| `~/.config/submitty/user` | Logged-in user ID | `submitty auth login` |

Environment variables override the saved files:

| Variable | Overrides |
|---|---|
| `SUBMITTY_SERVER` | Saved server URL |
| `SUBMITTY_TOKEN` | Saved token |

If no server has been saved and `SUBMITTY_SERVER` is not set, the server defaults to `http://localhost` (useful when running directly on the Submitty host).

## Usage

```
submitty [--format {table,json}] <command> <subcommand> [args]
```

Every command supports `--format json` for scripting:

```bash
submitty --format json course list | jq '.[].key'
```

## Command reference

Commands marked ✅ are implemented. Commands marked 🔲 are planned.

### auth — Authentication

```bash
submitty auth login <user> --server <url>  # ✅ Log in; saves server URL, token, and user
submitty auth login <user>                 # ✅ Log in using the saved or default server
submitty auth token                        # ✅ Print the active API token
submitty auth status                       # ✅ Validate the token against the server
submitty auth logout                       # ✅ Invalidate the token and remove cached files
```

### course — Course management

```bash
submitty course list                                                         # ✅ List all courses
submitty course create <term> <course> --instructor <user> [--group <grp>]  # ✅ Create a course
submitty course config get <term> <course>                                   # ✅ Show course config (JSON)
submitty course config set <term> <course> <name> <value>                   # ✅ Update one config value
```

### term — Term management

```bash
submitty term list                     # 🔲 List all terms
submitty term create <key> <name>      # 🔲 Create a new term (replaces create_term.sh)
```

### user — User management

```bash
submitty user list                                    # 🔲 List all Submitty users
submitty user add <user_id> [--email] [--name]        # 🔲 Create a new user (replaces adduser.py)
submitty user enroll <user_id> <term> <course>        # 🔲 Enroll a user in a course (replaces adduser_course.py)
submitty user unenroll <user_id> <term> <course>      # 🔲 Remove a user from a course
```

### report — Grade reporting

```bash
submitty report summary <term> <course>              # 🔲 Generate grade summaries (replaces generate_grade_summaries.py)
submitty report rainbow build <term> <course>        # 🔲 Run rainbow grades (replaces auto_rainbow_grades.py)
submitty report rainbow schedule <term> <course>     # 🔲 Manage rainbow grade schedule (replaces auto_rainbow_scheduler.py)
```

### worker — Autograding worker management

```bash
submitty worker list                   # 🔲 List worker machines and status
submitty worker restart                # 🔲 Restart shipper and all workers (replaces restart_shipper_and_all_workers.py)
submitty worker stop                   # 🔲 Stop shipper and all workers (replaces killall_shippers_and_workers.sh)
submitty worker sysinfo                # 🔲 Push updated system info for all workers (replaces update_worker_sysinfo.sh)
submitty worker repair                 # 🔲 Restart any inactive core services (replaces repair_services.sh)
```

### docker — Docker image management

```bash
submitty docker cleanup                # 🔲 Remove unused Docker images (replaces docker_cleanup.sh)
```

### notification — Notifications and email

```bash
submitty notification send <user> <message>          # 🔲 Send a notification (replaces send_notification.py)
submitty email cleanup                               # 🔲 Remove old email records (replaces cleanup_old_email.py)
```

### admin — System administration

```bash
submitty admin check                   # 🔲 Verify installation and course data (replaces check_everything.py)
submitty admin session cleanup         # 🔲 Delete expired sessions (replaces delete_expired_sessions.py)
submitty admin anonymize <term> <course>             # 🔲 Assign anonymous IDs for a gradeable (replaces anonymize.py)
submitty admin version                 # 🔲 Show Submitty version details (replaces get_version_details.py)
```

## Development

### Project layout

```
src/submitty_cli/
  config.py        Loads server URL and token from ~/.config/submitty/ → SubmittyConfig
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
# With uv (no venv activation needed)
uv run pytest
uv run pytest -v
uv run pytest --cov
uv run pytest tests/submitty_cli/commands/test_auth.py

# With an activated venv
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
| `api_token_generate.php` | `submitty auth token` | ✅ Done |
| `create_course.sh` | `submitty course create` | ✅ Done |
| `create_term.sh` | `submitty term create` | 🔲 Planned |
| `adduser.py` | `submitty user add` | 🔲 Planned |
| `adduser_course.py` | `submitty user enroll` | 🔲 Planned |
| `generate_grade_summaries.py` | `submitty report summary` | 🔲 Planned |
| `auto_rainbow_grades.py` | `submitty report rainbow build` | 🔲 Planned |
| `auto_rainbow_scheduler.py` | `submitty report rainbow schedule` | 🔲 Planned |
| `restart_shipper_and_all_workers.py` | `submitty worker restart` | 🔲 Planned |
| `killall_shippers_and_workers.sh` | `submitty worker stop` | 🔲 Planned |
| `update_worker_sysinfo.sh` | `submitty worker sysinfo` | 🔲 Planned |
| `repair_services.sh` | `submitty worker repair` | 🔲 Planned |
| `docker_cleanup.sh` | `submitty docker cleanup` | 🔲 Planned |
| `send_notification.py` | `submitty notification send` | 🔲 Planned |
| `cleanup_old_email.py` | `submitty email cleanup` | 🔲 Planned |
| `delete_expired_sessions.py` | `submitty admin session cleanup` | 🔲 Planned |
| `check_everything.py` | `submitty admin check` | 🔲 Planned |
| `anonymize.py` | `submitty admin anonymize` | 🔲 Planned |
| `anonymize_autograding_logs.py` | `submitty admin anonymize` | 🔲 Planned |
| `get_version_details.py` | `submitty admin version` | 🔲 Planned |
| `authentication.py` | internal library (no CLI replacement) | — |
| `database_queries.py` | internal library (no CLI replacement) | — |
| `send_email.py` | internal daemon (no CLI replacement) | — |
| `build_config_upload.py` | internal daemon (no CLI replacement) | — |
| `replay_experiment_script.sh` | experimental (no CLI replacement) | — |
