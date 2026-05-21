"""HTTP client wrapper for the Submitty REST API."""
from __future__ import annotations

import httpx


class APIError(Exception):
    """Raised when the API returns an unexpected error response."""

    def __init__(self, message: str, status_code: int = 0) -> None:
        super().__init__(message)
        self.status_code = status_code


class AuthError(APIError):
    """Raised on HTTP 401 — token is missing, invalid, or expired."""


class NotFoundError(APIError):
    """Raised on HTTP 404 — the requested resource does not exist."""


class SubmittyClient:
    """Thin httpx wrapper that injects Bearer auth and maps HTTP errors to exceptions."""

    def __init__(self, base_url: str, token: str) -> None:
        if not token:
            raise APIError(
                "API token is empty — run 'submitty auth login <user>' or set SUBMITTY_TOKEN"
            )
        self._http = httpx.Client(
            base_url=base_url,
            headers={"Authorization": token},
            timeout=30.0,
        )

    def _raise_for_status(self, response: httpx.Response) -> None:
        """Translate HTTP error codes and application-level failures into typed exceptions."""
        if response.status_code == 401:
            raise AuthError("Authentication failed — check your token", 401)
        if response.status_code == 404:
            raise NotFoundError("Resource not found", 404)
        if response.is_error:
            try:
                message = response.json().get("message", response.text)
            except ValueError:
                message = response.text
            raise APIError(f"API error {response.status_code}: {message}", response.status_code)
        try:
            body = response.json()
            if body.get("status") == "fail":
                raise APIError(body.get("message", "Request failed"), response.status_code)
        except (ValueError, AttributeError):
            pass

    def get(self, path: str, **kwargs: object) -> dict:
        """Send a GET request and return the parsed JSON body."""
        response = self._http.get(path, **kwargs)
        self._raise_for_status(response)
        return response.json()

    def post(self, path: str, **kwargs: object) -> dict:
        """Send a POST request and return the parsed JSON body."""
        response = self._http.post(path, **kwargs)
        self._raise_for_status(response)
        return response.json()

    def put(self, path: str, **kwargs: object) -> dict:
        """Send a PUT request and return the parsed JSON body."""
        response = self._http.put(path, **kwargs)
        self._raise_for_status(response)
        return response.json()

    def close(self) -> None:
        """Close the underlying HTTP connection pool."""
        self._http.close()

    def __enter__(self) -> "SubmittyClient":
        return self

    def __exit__(self, *args: object) -> None:
        self.close()
