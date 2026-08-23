#!/usr/bin/env python3
"""Secure Docker container execution for custom clustering algorithms."""

import json
import os
import shutil
import tempfile
import docker
import requests


CONTAINER_TIMEOUT = 120  # seconds wall-clock timeout
CPU_LIMIT = 60  # seconds CPU time  
DATA_LIMIT = 536870912  # 512MB in bytes
MEMORY_LIMIT = '512m'  # hard memory ceiling enforced by the kernel
PIDS_LIMIT = 128  # guards against fork bombs in uploaded code
CUSTOM_SCRIPT_NAME = 'custom_algorithm.py'

# The dedicated Docker image for running custom clustering algorithms.
# Built from Submitty/DockerImages (dockerfiles/grading-clustering) and
# pre-loaded with numpy, pandas, scikit-learn, and scipy.
CLUSTERING_DOCKER_IMAGE = 'submitty/grading-clustering:latest'


def execute_custom_clustering(script_path, input_data):
    """
    Execute a custom clustering script inside a secure Docker container.\
    """
    work_dir = tempfile.mkdtemp(prefix='clustering_')

    try:
        # Write input data for the custom script
        input_path = os.path.join(work_dir, 'input.json')
        with open(input_path, 'w') as f:
            json.dump(input_data, f)

        dest_script = os.path.join(work_dir, CUSTOM_SCRIPT_NAME)
        shutil.copy2(script_path, dest_script)
        os.chmod(work_dir, 0o770)
        os.chmod(input_path, 0o660)
        os.chmod(dest_script, 0o660)
        _run_container(work_dir)

        # Read and validate output
        output_path = os.path.join(work_dir, 'output.json')
        if not os.path.exists(output_path):
            raise RuntimeError(
                "Custom script did not produce output.json. "
                "Ensure your script writes results to 'output.json' in the working directory."
            )

        with open(output_path, 'r') as f:
            output_data = json.load(f)

        return _validate_output(output_data)

    finally:
        shutil.rmtree(work_dir, ignore_errors=True)


def _run_container(work_dir):
    """
    Create and run a Docker container to execute the custom clustering script.
    """
    client = docker.from_env(timeout=CONTAINER_TIMEOUT + 30)
    container = None

    try:
        ulimits = [
            docker.types.Ulimit(name='cpu', soft=CPU_LIMIT, hard=CPU_LIMIT),
            docker.types.Ulimit(name='data', soft=DATA_LIMIT, hard=DATA_LIMIT),
        ]

        mount = {
            work_dir: {
                'bind': work_dir,
                'mode': 'rw'
            }
        }

        container = client.containers.create(
            CLUSTERING_DOCKER_IMAGE,
            command=['python3', os.path.join(work_dir, CUSTOM_SCRIPT_NAME)],
            ulimits=ulimits,
            mem_limit=MEMORY_LIMIT,
            pids_limit=PIDS_LIMIT,
            network='none',
            user=str(os.getuid()),
            volumes=mount,
            working_dir=work_dir,
            name=f'clustering_{os.path.basename(work_dir)}',
            stdin_open=False,
            tty=False,
        )

        container.start()

        try:
            result = container.wait(timeout=CONTAINER_TIMEOUT)
        except Exception as e:
            if _is_timeout(e):
                try:
                    container.kill()
                except docker.errors.APIError:
                    pass
                raise RuntimeError(
                    f"Custom clustering script exceeded the {CONTAINER_TIMEOUT}s time limit. "
                    "Optimize your algorithm or reduce the dataset size."
                )
            raise

        exit_code = result.get('StatusCode', -1)
        stdout_logs = container.logs(stdout=True, stderr=False, tail=50).decode('utf-8', errors='replace')
        stderr_logs = container.logs(stdout=False, stderr=True, tail=50).decode('utf-8', errors='replace')

        if exit_code != 0:
            error_detail = stderr_logs[:1000] if stderr_logs else stdout_logs[:1000]
            raise RuntimeError(
                f"Custom clustering script exited with code {exit_code}.\n"
                f"Script output:\n{error_detail}"
            )

    except docker.errors.ImageNotFound:
        raise RuntimeError(
            f"Docker image '{CLUSTERING_DOCKER_IMAGE}' is not available on this machine. "
            "Ask your system administrator to pull it before running custom clustering algorithms."
        )
    except docker.errors.ContainerError as e:
        raise RuntimeError(f"Container execution error: {e}")
    except docker.errors.APIError as e:
        raise RuntimeError(f"Docker API error while running the clustering container: {e}")
    finally:
        if container is not None:
            try:
                container.remove(force=True)
            except Exception:
                pass
        client.close()


def _is_timeout(error):
    """Detect the timeout raised by container.wait() across docker-py versions."""
    if isinstance(error, requests.exceptions.Timeout):
        return True
    return 'timed out' in str(error).lower()


def _validate_output(output_data):
    """
    Validate the output.json structure from the custom script.
    """
    if not isinstance(output_data, dict):
        raise RuntimeError(
            "Invalid output.json: expected a JSON object at the top level."
        )

    clusters = output_data.get('clusters')
    if clusters is None:
        raise RuntimeError(
            'Invalid output.json: missing required "clusters" key. '
            'Expected format: {"clusters": {"Name": [submitters...]}}'
        )

    if not isinstance(clusters, dict):
        raise RuntimeError(
            'Invalid output.json: "clusters" must be a JSON object '
            'mapping cluster names to arrays of submitters.'
        )

    normalized = {}
    for cluster_name, members in clusters.items():
        if not isinstance(cluster_name, str):
            raise RuntimeError(
                f"Invalid output.json: cluster name must be a string, got {type(cluster_name).__name__}."
            )
        if not isinstance(members, list):
            raise RuntimeError(
                f'Invalid output.json: members of cluster "{cluster_name}" must be an array.'
            )

        normalized_members = []
        for i, member in enumerate(members):
            if not isinstance(member, dict):
                raise RuntimeError(
                    f'Invalid output.json: member {i} in cluster "{cluster_name}" must be a JSON object.'
                )

            user_id = member.get('user_id')
            team_id = member.get('team_id')
            if user_id is None and team_id is None:
                raise RuntimeError(
                    f'Invalid output.json: member {i} in cluster "{cluster_name}" '
                    'must have either "user_id" or "team_id".'
                )
            # The database enforces exactly one of the two being set.
            if user_id is not None and team_id is not None:
                raise RuntimeError(
                    f'Invalid output.json: member {i} in cluster "{cluster_name}" '
                    'must have exactly one of "user_id" or "team_id", not both.'
                )

            if 'active_version' not in member:
                raise RuntimeError(
                    f'Invalid output.json: member {i} in cluster "{cluster_name}" '
                    'must have "active_version".'
                )
            try:
                active_version = int(member['active_version'])
            except (TypeError, ValueError):
                raise RuntimeError(
                    f'Invalid output.json: "active_version" for member {i} in cluster '
                    f'"{cluster_name}" must be an integer.'
                )

            # Return only the keys the database layer needs, always present, so
            # a partial member dict cannot raise a KeyError during insertion.
            normalized_members.append({
                'user_id': user_id,
                'team_id': team_id,
                'active_version': active_version,
            })

        normalized[cluster_name] = normalized_members

    if not any(normalized.values()):
        raise RuntimeError(
            'Custom script produced no cluster members. Refusing to overwrite '
            'the existing clustering with an empty result.'
        )

    return normalized
