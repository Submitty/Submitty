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


# ==================================================================================
# Tells a foreign autograding worker to reinstall.
def install_worker(user, host):
    command = "sudo {0}".format(os.path.join(SUBMITTY_INSTALL_DIR, ".setup", "INSTALL_SUBMITTY.sh"))
    return run_commands_on_worker(user, host, [command,], 'installation' )

# ==================================================================================
# Tells a worker to update its docker container dependencies
def update_docker_images(user, host, worker, autograding_workers, autograding_containers):
    images_to_update = set()
    worker_requirements = autograding_workers[worker]['capabilities']

    success = True
    print(f'download/update docker images on {host}')

    for requirement, images in autograding_containers.items():
        if requirement in worker_requirements:
            images_to_update.update(set(images))

    images_str = ", ".join(str(e) for e in images_to_update)
    print(f'{host} needs {images_str}')
    #if we are updating the current machine, we can just move the new json to the appropriate spot (no ssh needed)
    if host == "localhost":
        get_sysinfo.print_distribution()
        client = docker.from_env()
        try:
            # List all images
            image_set = {
                image_name
                for image in client.images.list()
                for image_name in image.attrs["RepoTags"]
            }
            print("Images found on machine:")
            for image in image_set:
                print(image)
            print("\n")
            image_ids = set([client.images.get(im).id for im in image_set])
            images_to_update_ids = set([client.images.get(im).id for im in images_to_update])
            images_to_remove = set.difference(image_ids, images_to_update_ids)

            # Prevent removal of system docker containers
            with open(os.path.join(SUBMITTY_REPOSITORY_DIR, ".setup", "data", "system_docker_containers.json")) as json_file:
                system_docker_containers = json.load(json_file)

            images_to_remove = set.difference(images_to_remove, set([client.images.get(im).id for im in system_docker_containers]))

            # Remove images
            for image_id in images_to_remove:
                try:
                    client.images.remove(image_id, True)
                    print(f"Removed image {image_id}")
                except Exception as e:
                    print(f"ERROR: An error occurred while removing image by ID {image_id}: {e}", file=sys.stderr)
                    traceback.print_exc(file=sys.stderr)

        except Exception as e:
            print(f"ERROR: An error occurred: {e}", file=sys.stderr)
            traceback.print_exc(file=sys.stderr)

        for image in images_to_update:
            print(f"locally pulling the image '{image}'")
            try:
                repo, tag = image.split(':')
                client.images.pull(repository=repo, tag=tag)
            except Exception as e:
              print(f"ERROR: Could not pull {image}: {e}", file=sys.stderr)
              traceback.print_exc(file=sys.stderr)

              # check for machine
              if platform.machine() == "aarch64":
                  # SEE GITHUB ISSUE #7885 - https://github.com/Submitty/Submitty/issues/7885
                  # docker pull often fails on ARM installation
                  print("WARNING: SKIPPING DOCKER PULL ERROR")
              else:
                  # normal case
                  success = False

        docker_info = client.info()
        docker_images_obj = client.images.list()
        #print the details of the image
        get_sysinfo.print_docker_info()
    else:
        shipperutil_path = os.path.join(SUBMITTY_INSTALL_DIR, "sbin", "shipper_utils")
        commands = list()
        script_directory = os.path.join(shipperutil_path, 'docker_command_wrapper.py')
        for image in images_to_update:
            commands.append(f'python3 {script_directory} {image}')
        commands.append(f"python3 {os.path.join(shipperutil_path, 'get_sysinfo.py')} docker osinfo")
        success = run_commands_on_worker(user, host, commands, operation='docker image update')

    return success


def run_commands_on_worker(user, host, commands, operation='unspecified operation'):
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
                print(f"WARNING: Timed out when trying to ssh to {user}@{host}\nskipping {host} machine...")
            else:
                print(f"ERROR: could not ssh to {user}@{host} due to following error: {str(e)}")
            return False
        try:
            success = True
            for command in commands:
                print(f'{host}: performing {command}')
                (_, stdout, _) = target_connection.exec_command(command, timeout=600)
                print(stdout.read().decode('utf-8'))
                status = int(stdout.channel.recv_exit_status())
                if status != 0:
                    print(f"ERROR: Failure performing {operation} on {user}@{host}")
                    success = False
        except Exception as e:
            print(f"ERROR: Failure performing {operation} on {host} due to error {str(e)}")
            success = False
        finally:
            target_connection.close()
            if intermediate_connection:
                intermediate_connection.close()
            return success

# Rsynch the local (primary) codebase to a worker machine.
def copy_code_to_worker(worker, user, host, submitty_repository):
    exit_code = run_systemctl_command(worker, 'status', False)
    if exit_code == 1:
        print(f"ERROR: {worker}'s worker daemon was active when before rsyncing began. Attempting to turn off.")
        exit_code = run_systemctl_command(worker, 'stop', False)
        if exit_code != 0:
            print(f"Could not turn off {worker}'s daemon. Please allow rsyncing to continue and then attempt another install.")
    elif exit_code == 4:
        print(f"WARNING: Connection to machine {worker} timed out. Skipping code copying...")
        return True

    local_directory = submitty_repository
    remote_host = '{0}@{1}'.format(user, host)
    foreign_directory = submitty_repository
    rsync_exclude = os.path.join(submitty_repository, ".setup", "worker_rsync_exclude.txt")

    # rsync the file
    print(f"performing rsync to {worker}...")
    # If this becomes too slow, we can exclude directories using --exclude.
    # e.g. --exclude=.git --exclude=.setup/data --exclude=site
    command = "rsync -a --exclude-from={3} --no-perms --no-o --omit-dir-times --no-g {0}/ {1}:{2}".format(
              local_directory, remote_host, foreign_directory, rsync_exclude).split()
    res = subprocess.run(command, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                         check=True, universal_newlines=True)
    if res.returncode != 0:
        print(f"rsync ended in error with code {res.returncode}\n {res.stderr}")
    else:
        print(res.stdout)

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


def update_machine(machine,stats,args):
    print(f"UPDATE MACHINE: {machine}")

    user = stats['username']
    host = stats['address']
    enabled = stats['enabled']
    primary = machine == 'primary' or host == 'localhost'

    if not enabled:
        print(f"Skipping update of {machine} because it is not enabled.")
        return False

    # We don't have to update the code for the primary machine or if docker_images is specified.
    if not primary and not args.docker_images:
        print("copy Submitty source code...")
        timed_out = copy_code_to_worker(machine, user, host, submitty_repository)
        if timed_out == True:
            print(f"WARNING: Connection to machine {machine} timed out. Skipping Submitty installation...")
            return True
        print("beginning installation...")
        success = install_worker(user, host)
        if success == False:
            print(f"ERROR: Failed to install Submitty software update on {machine}")
            return False

    # Install/update docker containers
    # do this before restarting the workers
    success = update_docker_images(user, host, machine, autograding_workers, autograding_containers)
    if success == False:
        print(f"ERROR: Failed to pull one or more required docker images on {machine}")
        return False
    return True

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

    with open(AUTOGRADING_CONTAINERS_PATH, 'r') as infile:
        autograding_containers = json.load(infile)

    submitty_repository = submitty_config['submitty_repository']

    print("-------------------------------------------------------")
    for machine, stats in autograding_workers.items():

        enabled = stats['enabled']
        if not enabled:
            print(f"SKIPPING UPDATE OF MACHINE {machine}")
            print(f"because it is not enabled")
        else:
            success = update_machine(machine,stats,args)
            if success == False:
                print(f"FAILURE TO UPDATE MACHINE {machine}")
                raise SystemExit("ERROR: FAILURE TO UPDATE ONE OR MORE MACHINES")
            else:
                print(f"SUCCESS UPDATING MACHINE {machine}")
        print("-------------------------------------------------------")
