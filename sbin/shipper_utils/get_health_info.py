import shutil
import subprocess

SYSTEMCTL_RC = ("Running", "Inactive (1)", "Inactive (2)", "Not Running (3)",
                "Service Not Found", "Unknown Error")


def service_status(service: str) -> int:
    return subprocess.run([
                "systemctl", "status", f"{service}", "--no-pager"
           ], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL).returncode


def disk_percentage() -> float:
    df = shutil.disk_usage("/")
    return float(df.used) / float(df.total)


def print_info() -> None:
    worker = min(service_status("submitty_autograding_worker"), len(SYSTEMCTL_RC) - 1)
    print("Worker Service:", SYSTEMCTL_RC[worker])

    shipper = min(service_status("submitty_autograding_shipper"), len(SYSTEMCTL_RC) - 1)
    print("Shipper Service:", SYSTEMCTL_RC[shipper])

    daemon = min(service_status("submitty_daemon_jobs_handler"), len(SYSTEMCTL_RC) - 1)
    print("Daemon Job Handler:", SYSTEMCTL_RC[daemon])

    print("Disk Usage: {0:.3f}%".format(disk_percentage() * 100))


if __name__ == "__main__":
    print_info()
