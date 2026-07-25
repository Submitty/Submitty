import os
import sys
import json
import argparse
import platform
import subprocess
import tempfile
import urllib.request
import generate_workers


def get_args():
    parser = argparse.ArgumentParser(
        description="Configure vagrant worker machines", prog="vagrant workers"
    )
    cmd_subparsers = parser.add_subparsers(dest="command", help="Available commands", required=True)

    generate_parser = cmd_subparsers.add_parser("generate", help="Generate worker configuration")
    generate_workers.add_args(generate_parser)

    socket_parser = cmd_subparsers.add_parser(
        "socket", help="Manage the networking socket for macOS with QEMU"
    )
    socket_subparsers = socket_parser.add_subparsers(
        dest="socket_command", help="Available commands"
    )
    socket_start_parser = socket_subparsers.add_parser("start", help="Start the socket")
    socket_start_parser.add_argument(
        "--public",
        default=False,
        action="store_true",
        help="Whether to expose the socket to the network",
    )
    socket_subparsers.add_parser("stop", help="Stop the socket")
    socket_restart_parser = socket_subparsers.add_parser(
        "restart", help="Stop and start the socket"
    )
    socket_restart_parser.add_argument(
        "--public",
        default=False,
        action="store_true",
        help="Whether to expose the socket to the network",
    )

    for misc_cmd in [
        "up",
        "halt",
        "status",
        "destroy",
        "provision",
        "ssh",
        "reload",
        "suspend",
        "snapshot",
        "resume",
    ]:
        misc_parser = cmd_subparsers.add_parser(misc_cmd)
        misc_parser.add_argument("remainder", nargs=argparse.REMAINDER)

    return parser.parse_args()


def load_config():
    try:
        with open(".vagrant/workers.json") as f:
            return json.load(f)
    except FileNotFoundError:
        print("No worker configuration has been generated")
        print("Please run 'vagrant workers generate'")
        sys.exit(1)


def start_socket(args, config, hbpath, svn_exe):
    pid_check = subprocess.run(["pgrep", "-f", svn_exe], stdout=subprocess.DEVNULL, check=False)
    if not pid_check.returncode:
        print("There is a socket server already running on this machine")
        print("Run 'vagrant workers socket stop' to stop it")
        sys.exit(1)
    if not os.path.exists(svn_exe):
        subprocess.check_call(["brew", "install", "socket_vmnet"])
    os.makedirs(os.path.join(hbpath, "var", "run"), 755, True)
    subprocess.check_call(["sudo", "echo", "Starting socket server..."])
    if args.public:
        print("Using public networking")
    with subprocess.Popen(
        [
            "sudo",
            svn_exe,
            f"--vmnet-mode={'shared' if args.public else 'host'}",
            f"--vmnet-gateway={config['gateway']}",
            os.path.join(hbpath, "var", "run", "socket_vmnet"),
        ],
        start_new_session=True,
        stdout=subprocess.DEVNULL,
    ) as _:
        pass
    print("Socket server running")
    sys.exit()


def stop_socket(svn_exe):
    pid_check = subprocess.run(["pgrep", "-f", svn_exe], stdout=subprocess.DEVNULL, check=False)
    if pid_check.returncode:
        print("Socket server is not running")
        sys.exit(1)
    subprocess.check_call(["sudo", "pkill", "-f", svn_exe])
    print("Successfully stopped socket server")
    sys.exit()


def restart_socket(args, config, hbpath, svn_exe):
    pkill_call = subprocess.run(["sudo", "pkill", "-f", svn_exe], check=False)
    if not pkill_call.returncode:
        print("Stopped running socket server")
    else:
        print("Socket server not running")
    start_socket(args, config, hbpath, svn_exe)


def handle_socket(args, config):
    if platform.system() != "Darwin":
        print("Socket networking is only for macOS using QEMU")
        sys.exit(1)
    if config["provider"] != "qemu":
        print(
            "Socket networking is only for QEMU. "
            f"Your configuration is set to use '{config['provider']}'"
        )
        sys.exit(1)
    if "gateway" not in config:
        print("Worker configuration is not valid, please run 'vagrant workers generate'")
        sys.exit(1)
    if "HOMEBREW_PREFIX" not in os.environ:
        print(
            "Homebrew has not been configured. "
            "Make sure to install homebrew and follow the instructions "
            "at the end to add it to your PATH"
        )
        sys.exit(1)
    hbpath = os.environ["HOMEBREW_PREFIX"]
    svn_exe = os.path.join(hbpath, "opt", "socket_vmnet", "bin", "socket_vmnet")
    if args.socket_command == "stop":
        stop_socket(svn_exe)
    elif args.socket_command == "start":
        start_socket(args, config, hbpath, svn_exe)
    elif args.socket_command == "restart":
        restart_socket(args, config, hbpath, svn_exe)


def handle_up(args, config, worker_env):
    if platform.system() != "Darwin" or config["provider"] != "qemu":
        up = subprocess.run(
            ["vagrant", "up", f"--provider={config['provider']}", *args.remainder],
            env=worker_env,
            check=False,
        )
        sys.exit(up.returncode)
    plugin_version = "24.07.00"
    plugin_info = subprocess.check_output(["vagrant", "plugin", "list", "--machine-readable"])
    if f"vagrant-qemu,plugin-version,{plugin_version}%" not in str(plugin_info):
        update_qemu_plugin(plugin_version)
    hbpath = os.environ["HOMEBREW_PREFIX"]
    svn_exe = os.path.join(hbpath, "opt", "socket_vmnet", "bin", "socket_vmnet")
    pid_check = subprocess.run(["pgrep", "-f", svn_exe], stdout=subprocess.DEVNULL, check=False)
    if pid_check.returncode:
        print("Socket server is not running, to start it run 'vagrant workers socket start'")
        return
    worker_env["GATEWAY_IP"] = config["gateway"]
    up = subprocess.run(
        [
            svn_exe + "_client",
            os.path.join(hbpath, "var", "run", "socket_vmnet"),
            "vagrant",
            "up",
            f"--provider={config['provider']}",
            *args.remainder,
        ],
        env=worker_env,
        check=False,
    )
    sys.exit(up.returncode)


def update_qemu_plugin(plugin_version):
    print("Updating QEMU plugin...")
    with tempfile.TemporaryDirectory() as tmpdir:
        with urllib.request.urlopen(
            f"https://github.com/Submitty/vagrant-qemu/releases/download/"
            f"v{plugin_version}/vagrant-qemu-{plugin_version}.gem"
        ) as res:
            if res.getcode() // 100 != 2:
                print(
                    "Failed to fetch vagrant-qemu plugin from github.com, "
                    f"server returned a status of {res.getcode()}"
                )
                sys.exit(1)
            gem_path = os.path.join(tmpdir, "pkg.gem")
            with open(gem_path, "wb") as f:
                f.write(res.read())
        subprocess.check_call(["vagrant", "plugin", "install", gem_path])
        print("Successfully updated plugin")


def main():
    args = get_args()
    if args.command == "generate":
        generate_workers.run(args)
        sys.exit()
    config = load_config()
    worker_env = os.environ.copy()
    worker_env["WORKER_MODE"] = "1"
    if args.command == "socket":
        handle_socket(args, config)
    elif args.command == "up":
        handle_up(args, config, worker_env)
    else:
        cmd = subprocess.run(["vagrant", args.command, *args.remainder],
                             env=worker_env, check=False)
        sys.exit(cmd.returncode)


if __name__ == "__main__":
    main()
