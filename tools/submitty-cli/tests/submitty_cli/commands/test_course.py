import json

from submitty_cli.cli import app
from submitty_cli.client import APIError


def test_course_list_table(runner, mock_state):
    mock_state.client.get.return_value = {
        "status": "success",
        "data": {
            "unarchived_courses": [
                {
                    "semester": "s25",
                    "title": "csci1200",
                    "display_name": "Data Structures",
                    "display_semester": "Spring 2025",
                },
                {
                    "semester": "s25",
                    "title": "csci2200",
                    "display_name": "Foundations of CS",
                    "display_semester": "Spring 2025",
                },
            ],
            "archived_courses": [],
        },
    }
    result = runner.invoke(app, ["course", "list"], obj=mock_state)
    assert result.exit_code == 0
    assert "csci1200" in result.output
    assert "csci2200" in result.output
    assert "Spring 2025" in result.output
    assert "s25" in result.output


def test_course_list_includes_archived(runner, mock_state):
    mock_state.client.get.return_value = {
        "status": "success",
        "data": {
            "unarchived_courses": [
                {"semester": "s25", "title": "csci1200", "display_name": "",
                 "display_semester": "Spring 2025"},
            ],
            "archived_courses": [
                {"semester": "f24", "title": "csci1200", "display_name": "",
                 "display_semester": "Fall 2024"},
            ],
        },
    }
    result = runner.invoke(app, ["course", "list"], obj=mock_state)
    assert result.exit_code == 0
    assert "Spring 2025" in result.output
    assert "s25" in result.output
    assert "Fall 2024" in result.output
    assert "f24" in result.output


def test_course_list_json_format(runner, mock_state):
    mock_state.client.get.return_value = {
        "status": "success",
        "data": {
            "unarchived_courses": [
                {"semester": "s25", "title": "csci1200", "display_name": "",
                 "display_semester": "Spring 2025"},
            ],
            "archived_courses": [],
        },
    }
    result = runner.invoke(app, ["--format", "json", "course", "list"], obj=mock_state)
    assert result.exit_code == 0
    parsed = json.loads(result.output)
    assert parsed[0]["title"] == "csci1200"


def test_course_list_empty(runner, mock_state):
    mock_state.client.get.return_value = {
        "status": "success",
        "data": {"unarchived_courses": [], "archived_courses": []},
    }
    result = runner.invoke(app, ["course", "list"], obj=mock_state)
    assert result.exit_code == 0


def test_course_create_success(runner, mock_state):
    mock_state.client.post.return_value = {"status": "success"}
    result = runner.invoke(
        app, ["course", "create", "s25", "csci1200", "--instructor", "prof01"], obj=mock_state
    )
    assert result.exit_code == 0
    assert "created" in result.output


def test_course_create_passes_correct_payload(runner, mock_state):
    mock_state.client.post.return_value = {"status": "success"}
    runner.invoke(
        app,
        ["course", "create", "s25", "csci1200", "--instructor", "prof01", "--group", "mygroup"],
        obj=mock_state,
    )
    _, kwargs = mock_state.client.post.call_args
    assert kwargs["data"]["course_semester"] == "s25"
    assert kwargs["data"]["course_title"] == "csci1200"
    assert kwargs["data"]["head_instructor"] == "prof01"
    assert kwargs["data"]["group_name"] == "mygroup"


def test_course_create_api_error_exits_nonzero(runner, mock_state):
    mock_state.client.post.side_effect = APIError("Course already exists", 409)
    result = runner.invoke(
        app, ["course", "create", "s25", "csci1200", "--instructor", "prof01"], obj=mock_state
    )
    assert result.exit_code == 1


def test_config_get_prints_json(runner, mock_state):
    mock_state.client.get.return_value = {
        "status": "success",
        "data": {"course_name": "Data Structures", "enabled": True},
    }
    result = runner.invoke(app, ["course", "config", "get", "s25", "csci1200"], obj=mock_state)
    assert result.exit_code == 0
    parsed = json.loads(result.output)
    assert parsed["course_name"] == "Data Structures"


def test_config_get_calls_correct_endpoint(runner, mock_state):
    mock_state.client.get.return_value = {"status": "success", "data": {}}
    runner.invoke(app, ["course", "config", "get", "s25", "csci1200"], obj=mock_state)
    mock_state.client.get.assert_called_once_with("/api/courses/s25/csci1200/config")


def test_config_set_success(runner, mock_state):
    mock_state.client.post.return_value = {"status": "success"}
    result = runner.invoke(
        app,
        ["course", "config", "set", "s25", "csci1200", "course_name", "Updated Name"],
        obj=mock_state,
    )
    assert result.exit_code == 0
    assert "course_name" in result.output


def test_config_set_sends_form_encoded(runner, mock_state):
    mock_state.client.post.return_value = {"status": "success"}
    runner.invoke(
        app,
        ["course", "config", "set", "s25", "csci1200", "course_name", "Updated Name"],
        obj=mock_state,
    )
    _, kwargs = mock_state.client.post.call_args
    assert kwargs["data"] == {"name": "course_name", "entry": "Updated Name"}


def test_config_set_api_error_exits_nonzero(runner, mock_state):
    mock_state.client.post.side_effect = APIError("Forbidden", 403)
    result = runner.invoke(
        app,
        ["course", "config", "set", "s25", "csci1200", "course_name", "x"],
        obj=mock_state,
    )
    assert result.exit_code == 1
