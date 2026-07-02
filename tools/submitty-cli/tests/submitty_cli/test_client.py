# pylint: disable=redefined-outer-name
import httpx
import pytest
import respx

from submitty_cli.client import APIError, AuthError, NotFoundError, SubmittyClient

BASE_URL = "https://submitty.example.com"


@pytest.fixture
def api_client() -> SubmittyClient:
    return SubmittyClient(BASE_URL, "test-token")


@respx.mock
def test_get_success(api_client):
    respx.get(f"{BASE_URL}/api/courses").mock(
        return_value=httpx.Response(200, json={"status": "success", "data": []})
    )
    result = api_client.get("/api/courses")
    assert result["status"] == "success"


@respx.mock
def test_get_sends_auth_header(api_client):
    route = respx.get(f"{BASE_URL}/api/courses").mock(
        return_value=httpx.Response(200, json={})
    )
    api_client.get("/api/courses")
    assert route.calls[0].request.headers["Authorization"] == "test-token"


@respx.mock
def test_get_401_raises_auth_error(api_client):
    respx.get(f"{BASE_URL}/api/token").mock(
        return_value=httpx.Response(401, json={"message": "Unauthorized"})
    )
    with pytest.raises(AuthError):
        api_client.get("/api/token")


@respx.mock
def test_get_404_raises_not_found(api_client):
    respx.get(f"{BASE_URL}/api/missing").mock(
        return_value=httpx.Response(404)
    )
    with pytest.raises(NotFoundError):
        api_client.get("/api/missing")


@respx.mock
def test_post_success(api_client):
    respx.post(f"{BASE_URL}/api/courses").mock(
        return_value=httpx.Response(200, json={"status": "success"})
    )
    result = api_client.post("/api/courses", json={"semester": "s25"})
    assert result["status"] == "success"


@respx.mock
def test_server_error_raises_api_error(api_client):
    respx.get(f"{BASE_URL}/api/something").mock(
        return_value=httpx.Response(500, json={"message": "Internal error"})
    )
    with pytest.raises(APIError) as exc_info:
        api_client.get("/api/something")
    assert exc_info.value.status_code == 500


@respx.mock
def test_auth_error_has_correct_status_code(api_client):
    respx.get(f"{BASE_URL}/api/token").mock(
        return_value=httpx.Response(401)
    )
    with pytest.raises(AuthError) as exc_info:
        api_client.get("/api/token")
    assert exc_info.value.status_code == 401


@respx.mock
def test_application_fail_status_raises_api_error(api_client):
    """HTTP 200 with status=fail in body should raise APIError."""
    respx.post(f"{BASE_URL}/api/courses").mock(
        return_value=httpx.Response(
            200, json={"status": "fail", "message": "You don't have access to this endpoint."}
        )
    )
    with pytest.raises(APIError) as exc_info:
        api_client.post("/api/courses", data={})
    assert "You don't have access" in str(exc_info.value)


@respx.mock
def test_application_fail_without_message_raises_api_error(api_client):
    respx.get(f"{BASE_URL}/api/something").mock(
        return_value=httpx.Response(200, json={"status": "fail"})
    )
    with pytest.raises(APIError):
        api_client.get("/api/something")
