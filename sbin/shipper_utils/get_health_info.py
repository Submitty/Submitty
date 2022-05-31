import shutil
import subprocess

SYSTEMCTL_RC = ("Running (0)", "Inactive (1)", "Inactive (2)", "Not Running (3)",
                "Service Unknown (4)", "Unknown Error")


def worker_service() -> int:
    return subprocess.run([
                "systemctl", "status", "submitty_autograding_worker", "--no-pager"
           ], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL).returncode


def shipper_service() -> int:
    return subprocess.run([
                "systemctl", "status", "submitty_autograding_shipper", "--no-pager"
           ], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL).returncode


def disk_percentage() -> float:
    df = shutil.disk_usage("/")
    return float(df.used) / float(df.total)


def print_info() -> None:
    worker = worker_service()
    if worker > len(SYSTEMCTL_RC) - 1:
        worker = len(SYSTEMCTL_RC) - 1
    print("Worker Service:", SYSTEMCTL_RC[worker])

    shipper = shipper_service()
    if shipper > len(SYSTEMCTL_RC) - 1:
        shipper = len(SYSTEMCTL_RC) - 1
    print("Shipper Service:", SYSTEMCTL_RC[shipper])

    print("Disk Usage: {0:.3f}%".format(disk_percentage() * 100))


if __name__ == "__main__":
    print_info()
