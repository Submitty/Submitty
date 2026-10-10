"""Utilities for interacting with databases"""

from urllib.parse import quote


def generate_connect_string(
    host: str,
    port: int,
    db: str,
    user: str,
    password: str,
) -> str:
    # The user and password are percent-encoded so that characters such as
    # '@', ':', '/' or '%' in them are not read as part of the URL syntax.
    user = quote(user, safe='')
    password = quote(password, safe='')
    conn_string = f"postgresql://{user}:{password}@"
    if not host.startswith('/'):
        conn_string += f"{host}:{port}"
    conn_string += f"/{db}"

    if host.startswith('/'):
        conn_string += f"?host={host}"

    return conn_string
