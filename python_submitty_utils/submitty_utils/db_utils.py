"""Utilities for interacting with databases"""


def generate_connect_string(
    host: str,
    port: int,
    db: str,
    user: str,
    password: str,
) -> str:
    conn_string = f"postgresql://{user}:{password}@"
    if not host.startswith('/'):
        conn_string += f"{host}:{port}"
    conn_string += f"/{db}"

    if host.startswith('/'):
        conn_string += f"?host={host}"

    return conn_string
