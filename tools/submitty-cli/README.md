# submitty-cli

A unified command-line interface for Submitty system administration. This tool is the long-term replacement for the collection of individual scripts in [`sbin/`](../../sbin/), consolidating them into a single, fully-tested CLI that operates through the [Submitty REST API](https://submitty.org/developer/api).

## Motivation

The `sbin/` directory contains dozens of standalone Python and shell scripts (`adduser.py`, `create_course.sh`, `generate_grade_summaries.py`, etc.) that each solve one problem in isolation. Over time this creates:

- **No shared test coverage** — individual scripts are difficult to unit test without a live server
- **Duplicated config loading** — every script re-reads config files in its own way
- **Inconsistent interfaces** — flags, output formats, and error codes vary between scripts
- **Fragmented documentation** — behavior lives only in the script itself

`submitty` replaces these scripts one at a time with subcommands that share a common HTTP client, output formatter, and test harness.

## Installation

### With uv (recommended)

[uv](https://docs.astral.sh/uv/) is the fastest way to get started — no manual venv management needed.

```bash
# Install uv (once, system-wide)
curl -LsSf https://astral.sh/uv/install.sh | sh

# From the tools/submitty-cli/ directory:
uv run pytest
uv run submitty auth status

# Install as a persistent global tool (like pipx)
uv tool install .
submitty --help
```

### With pip

```bash
pip install -e ".[dev]"
```

## Configuration

The CLI requires no config files. Set the server and token via environment variables:

| Variable | Purpose |
|---|---|
| `SUBMITTY_SERVER` | Base URL of the Submitty server (default: `http://localhost`) |
| `SUBMITTY_TOKEN` | API token for authentication |

After running `submitty auth login` (coming in a follow-up PR), the server URL and token are saved to `~/.config/submitty/` automatically.

## Usage

```
submitty [--format {table,json}] <command> <subcommand> [args]
```

### auth — Authentication

```bash
submitty auth token    # Print the active API token
submitty auth status   # Validate the token against the server
```

## Development

### Project layout

```
src/submitty_cli/
  config.py        Token resolution (env var → ~/.config/submitty/token)
  client.py        httpx-based API client; raises AuthError / NotFoundError / APIError
  state.py         AppState — lazy config + client, injected via Typer context
  output.py        OutputFormat enum, print_table / print_json / print_error
  cli.py           Root Typer app; registers command groups
  commands/
    auth.py        auth token / auth status

tests/submitty_cli/          mirrors src/submitty_cli/ (no __init__.py)
  conftest.py      Shared fixtures: sample_config, mock_client, mock_state, runner
  test_cli.py
  test_config.py
  test_client.py
  commands/
    test_auth.py
```

### Running tests

```bash
uv run pytest
uv run pytest -v
uv run pytest --cov
```

### Adding a new command

1. Create `src/submitty_cli/commands/<noun>.py` with a `<noun>_app = typer.Typer()`.
2. Register it in `src/submitty_cli/cli.py`:
   ```python
   from submitty_cli.commands.<noun> import <noun>_app
   app.add_typer(<noun>_app, name="<noun>", help="...")
   ```
3. Create the matching test file at `tests/submitty_cli/commands/test_<noun>.py`.
4. Access the HTTP client through `ctx.obj` (an `AppState`):
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
