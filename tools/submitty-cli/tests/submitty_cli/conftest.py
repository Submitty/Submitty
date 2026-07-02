# pylint: disable=redefined-outer-name
from unittest.mock import MagicMock

import pytest
from typer.testing import CliRunner

from submitty_cli.config import SubmittyConfig
from submitty_cli.state import AppState


@pytest.fixture
def sample_config() -> SubmittyConfig:
    return SubmittyConfig(
        server_url="https://submitty.example.com",
        token="test-token-abc123",
    )


@pytest.fixture
def mock_client() -> MagicMock:
    return MagicMock()


@pytest.fixture
def mock_state(sample_config: SubmittyConfig, mock_client: MagicMock) -> AppState:
    return AppState.for_testing(sample_config, mock_client)


@pytest.fixture
def runner() -> CliRunner:
    return CliRunner()
