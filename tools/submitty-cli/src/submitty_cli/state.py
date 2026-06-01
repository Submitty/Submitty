"""AppState: lazily loaded config and HTTP client, threaded through Typer's ctx.obj."""
from __future__ import annotations

from dataclasses import dataclass, field
from typing import Optional

from submitty_cli.client import SubmittyClient
from submitty_cli.config import SubmittyConfig, load_server, resolve_token
from submitty_cli.output import OutputFormat


@dataclass
class AppState:
    """Shared runtime state passed through Typer's context object to every command."""
    fmt: OutputFormat = OutputFormat.TABLE
    _config: Optional[SubmittyConfig] = field(default=None, repr=False)
    _client: Optional[SubmittyClient] = field(default=None, repr=False)

    @classmethod
    def for_testing(cls, config: SubmittyConfig, client: SubmittyClient) -> "AppState":
        """Create an AppState with pre-built config and client for use in tests."""
        state = cls()
        state._config = config  # pylint: disable=protected-access
        state._client = client  # pylint: disable=protected-access
        return state

    @property
    def server_url(self) -> str:
        """Return the server URL without requiring a token."""
        if self._config is not None:
            return self._config.server_url
        return load_server()

    @property
    def config(self) -> SubmittyConfig:
        """Load config on first access."""
        if self._config is None:
            self._config = SubmittyConfig(
                server_url=load_server(),
                token=resolve_token(),
            )
        return self._config

    @property
    def client(self) -> SubmittyClient:
        """Build the API client on first access."""
        if self._client is None:
            self._client = SubmittyClient(self.config.server_url, self.config.token)
        return self._client
