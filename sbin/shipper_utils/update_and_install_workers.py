#!/usr/bin/env python3 -u

import sys
import os
from os import path
import json
import subprocess
import docker
import traceback
import argparse
import get_sysinfo
from submitty_utils import ssh_proxy_jump
import platform
import threading


CONFIG_PATH = path.join(path.dirname(path.realpath(__file__)), '..', '..','config')
SUBMITTY_CONFIG_PATH = path.join(CONFIG_PATH, 'submitty.json')
AUTOGRADING_WORKERS_PATH = path.join(CONFIG_PATH, 'autograding_workers.json')
AUTOGRADING_CONTAINERS_PATH = path.join(CONFIG_PATH, 'autograding_containers.json')
with open(os.path.join(CONFIG_PATH, 'submitty_users.json')) as open_file:
    OPEN_JSON = json.load(open_file)
DAEMON_UID = OPEN_JSON['daemon_uid']

with open(os.path.join(SUBMITTY_CONFIG_PATH)) as open_file:
    SUBMITTY_CONFIG = json.load(open_file)
SUBMITTY_INSTALL_DIR = SUBMITTY_CONFIG['submitty_install_dir']
SUBMITTY_REPOSITORY_DIR = SUBMITTY_CONFIG['submitty_repository']

SYSTEMCTL_WRAPPER_SCRIPT = os.path.join(SUBMITTY_INSTALL_DIR, 'sbin', 'shipper_utils','systemctl_wrapper.py')

# Functions to highlight important part of the output
def print_red(msg):
    return '\x1b[0;31m'+msg+'\x1b[0m'

def print_green(msg):
    return '\x1b[1;32m'+msg+'\x1b[0m'

def print_yellow(msg):
    return '\x1b[1;33m'+msg+'\x1b[0m'

class MachineUpdateThread(threading.Thread):
    def __init__(self, machine, stats, args):
        threading.Thread.__init__(self, name = 'Updater-'+machine)
        self.machine = machine
        self.stats = stats
        self.args = args
        self.msg = ""
        self.success = False
    def run(self):
        print(f"Starting thread for machine: {get_machine_by_ip(self.stats['address'])}. Full output will print when all threads complete.")
        self.success = update_machine(self.machine,self.stats,self.args, self)
        if self.success == False:
            self.add_message(print_red(f"FAILURE TO UPDATE MACHINE {get_machine_by_ip(self.stats['address'])}"))
            raise SystemExit("ERROR: FAILURE TO UPDATE ONE OR MORE MACHINES")
        else:
            self.add_message(print_green(f"SUCCESS UPDATING MACHINE {get_machine_by_ip(self.stats['address'])}"))
    def add_message(self, text):
        self.msg += text + "\n"
# IDEA: save worker prints separately and pass up to print in order later, or save to logs or both

# ==================================================================================
# Tells a foreign autograding worker to reinstall.
def install_worker(user, host, machine, thread_object: MachineUpdateThread):
    command = "sudo {0}".format(os.path.join(SUBMITTY_INSTALL_DIR, ".setup", "INSTALL_SUBMITTY.sh"))
    return run_commands_on_worker(user, host, machine, [command,], 'installation', thread_object)

# ==================================================================================
# Tells a worker to update its docker container dependencies
def update_docker_images(user, host, worker, autograding_workers, autograding_containers, thread_object: MachineUpdateThread):
    images_to_update = set()
    worker_requirements = autograding_workers[worker]['capabilities']

    success = True
    thread_object.add_message(f'{get_machine_by_ip(host)}: download/update docker images')

    for requirement, images in autograding_containers.items():
        if requirement in worker_requirements:
            images_to_update.update(set(images))

    images_str = ", ".join(str(e) for e in images_to_update)
    thread_object.add_message(f'{host} needs {images_str}')
    #if we are updating the current machine, we can just move the new json to the appropriate spot (no ssh needed)
    if host == "localhost":
        get_sysinfo.print_distribution()
        client = docker.from_env()
        try:
            image_id_to_tags = {}
            tag_to_image_id = {}
            for image in client.images.list():
                tags = image.attrs.get("RepoTags") or []
                for tag in tags:
                    tag_to_image_id[tag] = image.id
                image_id_to_tags.setdefault(image.id, []).extend(tags)

            image_tags = set(tag_to_image_id.keys())
            images_to_remove = set.difference(image_tags, images_to_update)

            # Prevent removal of system docker containers
            with open(os.path.join(SUBMITTY_REPOSITORY_DIR, ".setup", "data", "system_docker_containers.json")) as json_file:
                system_docker_containers = json.load(json_file)

            images_to_remove = set.difference(images_to_remove, set(system_docker_containers))

            # Remove images
            for imageRemoved in images_to_remove:
                try:
                    image_id = tag_to_image_id.get(imageRemoved)
                    ref_tags = image_id_to_tags.get(image_id, [])
                    if len(ref_tags) > 1:
                        client.images.remove(imageRemoved)
                    else:
                        client.images.remove(image_id)
                    thread_object.add_message("Removed image " + imageRemoved)
                except docker.errors.ImageNotFound:
                    thread_object.add_message(print_red(f"ERROR: Couldn't find image {imageRemoved}"))
                    continue
                except Exception as e:
                    thread_object.add_message(print_red(f"ERROR: An error occurred while removing image by ID {image_id}: {e}"))
                    traceback.print_exc(file=sys.stderr)

        except Exception as e:
            thread_object.add_message(print_red(f"ERROR: An error occurred: {e}"))
            traceback.print_exc(file=sys.stderr)

        for image in images_to_update:
            thread_object.add_message(f"{get_machine_by_ip(host)}: locally pulling the image '{image}'")
            try:
                repo, tag = image.split(':')
                client.images.pull(repository=repo, tag=tag)
            except Exception as e:
              thread_object.add_message(print_red(f"{get_machine_by_ip(host)}: ERROR: Could not pull {image}: {e}"))
              traceback.print_exc()

              # check for machine
              if platform.machine() == "aarch64":
                  # SEE GITHUB ISSUE #7885 - https://github.com/Submitty/Submitty/issues/7885
                  # docker pull often fails on ARM installation
                  thread_object.add_message(print_yellow(f"{get_machine_by_ip(host)}: WARNING: SKIPPING DOCKER PULL ERROR"))
              else:
                  # normal case
                  success = False

        docker_info = client.info()
        docker_images_obj = client.images.list()
        #print the details of the image
        get_sysinfo.print_docker_info()
    else:
        commands = list()
        shipperutil_path = os.path.join(SUBMITTY_INSTALL_DIR, "sbin", "shipper_utils")
        commands = list()
        script_directory = os.path.join(shipperutil_path, 'docker_command_wrapper.py')
        for image in images_to_update:
            commands.append(f'python3 {script_directory} {image}')
        commands.append(f"python3 {os.path.join(shipperutil_path, 'get_sysinfo.py')} docker osinfo")
        success = run_commands_on_worker(user, host, machine, commands, operation='docker image update', thread_object=thread_object)

    return success


def run_commands_on_worker(user, host, machine, commands, operation='unspecified operation', thread_object: MachineUpdateThread = None):
    #if we are updating the current machine, we can just move the new json to the appropriate spot (no ssh needed)
    if host == "localhost":
        return True
    else:
        success = False
        try:
            (target_connection,
             intermediate_connection) = ssh_proxy_jump.ssh_connection_allowing_proxy_jump(user,host)
        except Exception as e:
            if str(e) == "timed out":
                thread_object.add_message(print_yellow(f"WARNING: Timed out when trying to ssh to {user}@{host}\nskipping {host} machine {machine}..."))
            else:
                thread_object.add_message(print_red(f"ERROR: could not ssh to {user}@{host} (machine {machine}) due to following error: {str(e)}"))
            return False
        try:
            success = True
            print(f"============Detailed Command Output for {machine} ============")
            for command in commands:
                thread_object.add_message(f'{get_machine_by_ip(host)}: performing {command}')
                (_, stdout, _) = target_connection.exec_command(command, timeout=600)
                
                lines = stdout.read().decode('utf-8').split("\n")
                print("\n".join(f"{get_machine_by_ip(host)}: {line}" for line in lines if line))
                status = int(stdout.channel.recv_exit_status())
                if status != 0:
                    print(f'{get_machine_by_ip(host)}: {command} failed!')
                    thread_object.add_message(print_red(f"ERROR: Failure performing {operation} on {user}@{host}"))
                    success = False
                else:
                    print(f'{get_machine_by_ip(host)}: {command} success!')
        except Exception as e:
            thread_object.add_message(print_red(f"ERROR: Failure performing {operation} on {host} due to error {str(e)}"))
            success = False
        finally:
            target_connection.close()
            if intermediate_connection:
                intermediate_connection.close()
            return success

# Rsynch the local (primary) codebase to a worker machine.
def copy_code_to_worker(worker, user, host, submitty_repository, thread_object: MachineUpdateThread):
    exit_code = run_systemctl_command(worker, 'status', False)
    if exit_code == 1:
        thread_object.add_message(print_yellow(f"ERROR: {get_machine_by_ip(host)}'s worker daemon was active when before rsyncing began. Attempting to turn off."))
        exit_code = run_systemctl_command(worker, 'stop', False)
        if exit_code != 0:
            thread_object.add_message(print_red(f"Could not turn off {get_machine_by_ip(host)}'s daemon. Please allow rsyncing to continue and then attempt another install."))
    elif exit_code == 4:
        thread_object.add_message(print_yellow(f"WARNING: Connection to machine {get_machine_by_ip(host)} timed out. Skipping code copying..."))
        return True

    local_directory = submitty_repository
    remote_host = '{0}@{1}'.format(user, host)
    foreign_directory = submitty_repository
    rsync_exclude = os.path.join(submitty_repository, ".setup", "worker_rsync_exclude.txt")

    # rsync the file
    thread_object.add_message(f"{get_machine_by_ip(host)}: performing rsync to {get_machine_by_ip(host)}...")
    # If this becomes too slow, we can exclude directories using --exclude.
    # e.g. --exclude=.git --exclude=.setup/data --exclude=site
    command = "rsync -a --exclude-from={3} --no-perms --no-o --omit-dir-times --no-g {0}/ {1}:{2}".format(
              local_directory, remote_host, foreign_directory, rsync_exclude).split()
    res = subprocess.run(command, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                         check=True, universal_newlines=True)
    if res.returncode != 0:
        thread_object.add_message(print_red(f"rsync ended in error with code {res.returncode}\n {res.stderr}"))
    else:
        thread_object.add_message(print_red(res.stdout))

def run_systemctl_command(machine, command, is_primary):
    command = [SYSTEMCTL_WRAPPER_SCRIPT, command, '--target', machine]
    process = subprocess.Popen(command)
    process.communicate()
    exit_code = process.wait()
    return exit_code

def parse_arguments():
    #parse arguments
    parser = argparse.ArgumentParser(description='This script facilitates automatically updating worker machines and managing their docker image dependencies',)
    parser.add_argument("--docker_images", action="store_true", default=False, help="When specified, only update docker images." )
    return parser.parse_args()


def update_machine(machine,stats,args, thread_object: MachineUpdateThread):
    thread_object.add_message(f"UPDATE MACHINE: {get_machine_by_ip(stats['address'])}\n")
    user = stats['username']
    host = stats['address']
    enabled = stats['enabled']
    primary = machine == 'primary' or host == 'localhost'

    if not enabled:
        thread_object.add_message(print_yellow(f"Skipping update of {get_machine_by_ip(host)} because it is not enabled."))
        return False

    # We don't have to update the code for the primary machine or if docker_images is specified.
    if not primary and not args.docker_images:
        thread_object.add_message(f"{get_machine_by_ip(host)}: copy Submitty source code...")
        timed_out = copy_code_to_worker(machine, user, host, submitty_repository, thread_object)
        if timed_out == True:
            thread_object.add_message(print_yellow(f"ERROR: Connection to machine {get_machine_by_ip(host)} timed out. Skipping Submitty installation..."))
            return False
        thread_object.add_message(f"{get_machine_by_ip(host)}: beginning installation...\n")
        success = install_worker(user, host, machine, thread_object)
        if success == False:
            thread_object.add_message(print_red(f"ERROR: Failed to install Submitty software update on {get_machine_by_ip(host)}"))
            return False

    # Install/update docker containers
    # do this before restarting the workers
    success = update_docker_images(user, host, machine, autograding_workers, autograding_containers, thread_object)
    if success == False:
        thread_object.add_message(print_red(f"ERROR: Failed to pull one or more required docker images on {get_machine_by_ip(host)}"))
        return False
    return True

# Represents a thread for updating one machine

if __name__ == "__main__":

    # verify the DAEMON_USER is running this script
    if not int(os.getuid()) == int(DAEMON_UID):
        raise SystemExit("ERROR: the update_and_install_workers.py script must be run by the DAEMON_USER")

    args = parse_arguments()

    if args.docker_images == True:
        print("Mode Set: only updating docker images.")

    with open(SUBMITTY_CONFIG_PATH, 'r') as infile:
        submitty_config = json.load(infile)

    with open(AUTOGRADING_WORKERS_PATH, 'r') as infile:
        autograding_workers = json.load(infile)

    # NEW: Build a mapping from ip address to machine name
    IP_TO_MACHINE = { stats['address']: machine for machine, stats in autograding_workers.items() }

    # NEW: Helper function to retrieve the machine name for a given ip address
    def get_machine_by_ip(ip):
        return IP_TO_MACHINE.get(ip, ip)

    with open(AUTOGRADING_CONTAINERS_PATH, 'r') as infile:
        autograding_containers = json.load(infile)

    submitty_repository = submitty_config['submitty_repository']

    threads: list[MachineUpdateThread] = []

    # Start a thread for each enabled machine
    for machine, stats in autograding_workers.items():

        enabled = stats['enabled']
        if not enabled:
            print_yellow(f"SKIPPING UPDATE OF MACHINE {machine} because it is not enabled")
        else:
            thread = MachineUpdateThread(machine, stats, args)
            threads.append(thread)
            thread.start()
    
    for thread in threads:
        thread.join()
    print("================== Simplified Output ==================")
    for thread in threads:
        print(thread.msg)
        if not thread.success:
            print_red(f"====================================================== ERROR: Machine {thread.machine} FAILED ======================================================")