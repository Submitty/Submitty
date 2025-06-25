import os
import shutil
import pwd
import json
import subprocess
from collections import OrderedDict

SUBMITTY_REPOSITORY = os.environ['SUBMITTY_REPOSITORY']
SUBMITTY_INSTALL_DIR = os.environ['SUBMITTY_INSTALL_DIR']
DAEMON_USER = os.environ['DAEMON_USER']
SUPERVISOR_USER = 'submitty'

vagrant_workers_path = os.path.join(SUBMITTY_REPOSITORY, '.vagrant', 'workers.json')
autograding_workers_path = os.path.join(SUBMITTY_INSTALL_DIR, 'config', 'autograding_workers.json')

print("Loading existing data...")
with open(vagrant_workers_path) as file:
    vagrant_workers_data = json.load(file, object_pairs_hook=OrderedDict)

with open(autograding_workers_path) as file:
    autograding_workers_data = json.load(file, object_pairs_hook=OrderedDict)

if 'version' in vagrant_workers_data and type(vagrant_workers_data['version']) is int:
    provider = vagrant_workers_data['provider']
    vagrant_workers_data = vagrant_workers_data['workers']
else:
    print("This script requires a worker configuration of v2 or greater. Please regenerate your configuration with 'vagrant workers generate'.")
    exit(1)
print("Done loading data")
print()

print("Generating SSH credentials...")
shutil.rmtree("/tmp/worker_keys", True)
os.makedirs("/tmp/worker_keys", 0o500)
daemon_stat = pwd.getpwnam(DAEMON_USER)
os.chown("/tmp/worker_keys", daemon_stat.pw_uid, daemon_stat.pw_gid)

DAEMON_HOME = os.path.realpath(subprocess.check_output(['su', DAEMON_USER, '-c', 'echo $HOME']).strip())
if not os.path.exists(DAEMON_HOME):
    print("Error: could not find home directory for daemon user")
    exit(1)

shutil.rmtree(os.path.join(DAEMON_HOME, b'.ssh'), True)
subprocess.run(['su', DAEMON_USER, '-c', "ssh-keygen -b 2048 -t rsa -f ~/.ssh/id_rsa -q -N ''"], check=True)
print("Done generating")
print()

ssh_config = ''
successful_machines = []
for name, data in vagrant_workers_data.items():
    print("Attempting to connect to " + name)
    shutil.copyfile(f"{SUBMITTY_REPOSITORY}/.vagrant/machines/{name}/{provider}/private_key", f"/tmp/worker_keys/{name}")
    os.chown(f"/tmp/worker_keys/{name}", daemon_stat.pw_uid, daemon_stat.pw_gid)
    os.chmod(f"/tmp/worker_keys/{name}", 0o400)
    w = subprocess.run(['su', DAEMON_USER, '-c', f"scp -i /tmp/worker_keys/{name} -o StrictHostKeyChecking=no ~/.ssh/id_rsa.pub root@{data['ip_addr']}:/tmp/workerkey"])
    w = subprocess.run(['su', DAEMON_USER, '-c', f"ssh -i /tmp/worker_keys/{name} -o StrictHostKeyChecking=no root@{data['ip_addr']} \"chown {SUPERVISOR_USER}:{SUPERVISOR_USER} /tmp/workerkey && su {SUPERVISOR_USER} -c \\\"mkdir -p ~/.ssh && mv /tmp/workerkey ~/.ssh/authorized_keys\\\"\""])
    if w.returncode == 0:
        print("Connected to " + name)
        successful_machines.append(name)
        ssh_config += f"Host {name}\n  HostName {data['ip_addr']}\n  IdentityFile ~/.ssh/id_rsa\n  User submitty\n"
    else:
        print("Failed to connect to " + name)

shutil.rmtree("/tmp/worker_keys", True)
print()

print("Updating SSH configuration...")
ssh_config_path = os.path.join(DAEMON_HOME, b'.ssh', b'config')
with open(ssh_config_path, 'w') as file:
    file.write(ssh_config)
os.chown(ssh_config_path, daemon_stat.pw_uid, daemon_stat.pw_gid)
print("Successfully updated")

print("Writing new autograding configuration...")
new_autograding_data = OrderedDict()
new_autograding_data['primary'] = autograding_workers_data['primary']

total = 0
enabled = 0
for name, data in vagrant_workers_data.items():
    worker_data = OrderedDict(autograding_workers_data['primary'])
    worker_data['username'] = SUPERVISOR_USER
    worker_data['address'] = data['ip_addr']
    if name not in successful_machines:
        worker_data['enabled'] = False
    else:
        enabled += 1
    total += 1
    new_autograding_data[name] = worker_data

with open(autograding_workers_path, 'w') as file:
    json.dump(new_autograding_data, file)
print(f"Configuration saved with {enabled}/{total} machines enabled")
print()

print("DONE")
