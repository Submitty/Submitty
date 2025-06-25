#!/usr/bin/env python

import argparse
import docker
import json
import shutil
from submitty_utils import ssh_proxy_jump as ssh
import subprocess
import os

SYSCTL_RC = ("Running", "Inactive (1)", "Inactive (2)", "Not Running (3)",
             "Service Not Found", "Unknown Error")


def service_status(service: str) -> int:
    return subprocess.run([
                "systemctl", "status", f"{service}", "--no-pager"
           ], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL).returncode


def disk_percentage() -> float:
    df = shutil.disk_usage("/")
    return float(df.used) / float(df.total)


def print_service_info() -> None:
    worker = min(service_status("submitty_autograding_worker"), len(SYSCTL_RC) - 1)
    print("Worker Service:", SYSCTL_RC[worker])

    shipper = min(service_status("submitty_autograding_shipper"), len(SYSCTL_RC) - 1)
    print("Shipper Service:", SYSCTL_RC[shipper])

    daemon = min(service_status("submitty_daemon_jobs_handler"), len(SYSCTL_RC) - 1)
    print("Daemon Job Handler:", SYSCTL_RC[daemon])


def print_disk_usage() -> None:
    print("Disk Usage: {0:.3f}%".format(disk_percentage() * 100))


def print_docker_info() -> bool:
    try:
        docker_client = docker.from_env()
        docker_info = docker_client.info()
        docker_images_obj = docker_client.images.list()

        # print the details of the image
        print(f"Docker Version: {docker_info['ServerVersion']}")
        for image in docker_images_obj:
            # rip relevant information
            data = image.attrs
            print("Tag: ", end="")
            print(', '.join(data["RepoTags"]))
            print(f"\t-id: {image.short_id}")
            print(f'\t-created: {data["Created"]}')
            print(f'\t-size: {data["Size"]}')

            digests = data.get("RepoDigests")
            if digests:
                full_digest = data["RepoDigests"][0]
                digest_parts = full_digest.split('@')
                if len(digest_parts) == 2:
                    digest = digest_parts[1]
                else:
                    digest = full_digest
            else:
                digest = "None"
            print(f'\t-digest: { digest }')
        return True
    except docker.errors.APIError:
        print("APIError was raised.")
    return False


def print_system_load() -> None:
    try:
        print(f"System Load: {os.getloadavg()}")
    except AttributeError:
        print("System Load: Error")

def get_distribution() -> None:
    subprc = subprocess.run(["lsb_release", "-d"],
                            stderr=subprocess.DEVNULL,
                            stdout=subprocess.PIPE)
    return subprc.stdout.decode("ascii")

def print_distribution() -> None:
    subprc = subprocess.run(["lsb_release", "-d"],
                            stderr=subprocess.DEVNULL,
                            stdout=subprocess.PIPE)
    print(subprc.stdout.decode("ascii"))


TASKS = {
            "service":  [print_service_info],
            "disk":     [print_disk_usage],
            "docker":   [print_docker_info],
            "sysload":  [print_system_load],
            "osinfo":   [print_distribution],
            "all":      [print_service_info, print_disk_usage,
                         print_docker_info, print_system_load,
                         print_distribution]
        }


def run_tasks(tasks: list) -> None:
    for task in tasks:
        for component in TASKS[task]:
            component()


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Get system information.")
    parser.add_argument("--workers", action="store_true", help="Update workers")
    parser.add_argument("--install-path", type=str, default="/usr/local/submitty",
                        help="Specify install directory for all machines")
    parser.add_argument("tasks", nargs='+', type=str, choices=TASKS.keys(),
                        help="Tasks to perform")

    args = parser.parse_args()

    daemon_uid = json.load(
        open(os.path.join(args.install_path, "config", "submitty_users.json"), 'r')
    )["daemon_uid"]

    if not args.workers:
        run_tasks(args.tasks)
    else:
        if int(os.getuid()) != int(daemon_uid):
            print("ERR: DAEMON_USER is required when retrieving worker info")
            exit(1)

        # Get Workers
        # workers in form [(name, enabled, address, username)]
        workers = list()
        with open(
            os.path.join(args.install_path, "config", "autograding_workers.json"), 'r'
        ) as workers_json:
            workers_json = json.load(workers_json)
            for w_name, w_info in workers_json.items():
                workers.append((w_name, w_info["enabled"], w_info["address"],
                                w_info["username"]))

        # Run Tasks for workers
        for name, enabled, addr, user in workers:
            print("------------------------------", flush=True)

            if not enabled:
                print(f"Skipping disabled {name}", flush=True)
                continue

            if name == "primary" or addr == "localhost":
                print(f"System Info :: {name}", flush=True)
                run_tasks(args.tasks)
                continue

            # try to connect to worker
            try:
                (target_conn, intermediate_conn) =                      \
                    ssh.ssh_connection_allowing_proxy_jump(user, addr)
            except Exception as e:
                if str(e) == "timed out":
                    print(f"WARN: Timed out for {name} ({user}@{addr})")
                else:
                    print(f"ERR: Could not connect to {name} ({user}@{addr})")
                continue

            # try to run tasks
            print(f"System Info :: {name}", flush=True)
            cmd = [
                    "python3",
                    os.path.join(
                        args.install_path, "sbin", "shipper_utils",
                        os.path.basename(__file__)
                    ),
                    " ".join(args.tasks)
                ]

            try:
                (_, out, err) = target_conn.exec_command(" ".join(cmd), timeout=10)
                print(out.read().decode("ascii"))
                if int(out.channel.recv_exit_status()) != 0:
                    print(f"ERR: Failed to perform '{cmd}' on {name}: ")
                    print({err.read().decode("ascii")})
            except Exception as e:
                print(f"ERR: Failed to perform '{cmd}' on {name}: {str(e)}")
            finally:
                target_conn.close()
                if intermediate_conn:
                    intermediate_conn.close()

        # for name, enabled, addr, user in workers
    # if args.workers
# if __name__ == "__main__"
