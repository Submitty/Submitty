#!/usr/bin/python3

import argparse
import glob
import ipaddress
import json
import os
import random
import subprocess
from collections import OrderedDict
from typing import Union, cast
from tempfile import TemporaryDirectory


def add_args(parser):
    parser.add_argument('-n', '--num', default=1, type=int,
                        help='Number of worker machines to configure')
    parser.add_argument('--ip-range', default='192.168.{}.0/24'.format(random.randint(100, 200)), type=ipaddress.ip_network,
                        help='IP address range for workers')
    parser.add_argument('--base-port', default=2240, type=int,
                        help='Base ssh port (ports will be assigned incrementally)')
    parser.add_argument('--mac-prefix', default='52:54:00', type=str,
                        help='MAC address prefix for workers (QEMU only)')


def get_args():
    parser = argparse.ArgumentParser(
        description='Script to generate configuration for '
        'development worker machines', prog='vagrant workers generate')
    add_args(parser)
    return parser.parse_args()


def update_bootstrap(workers):
    print('Updating Bootstrap configuration... (see log below)')
    print('---')
    body = ['%%\n']
    for name, data in workers.items():
        body.append(name + ' 1 ' + data['ip_addr'] + ' ' + data['mac_addr'] + '\n')
    with TemporaryDirectory() as tmpdir:
        os.chdir(tmpdir)
        filepath = os.path.join(tmpdir, 'bootpd')
        with open(filepath, 'x') as file:
            file.writelines(body)
        w1 = subprocess.run(['sudo', 'mv', filepath, '/etc/bootptab'])
        if w1.returncode != 0:
            print('\033[31mFailed to save configuration\033[0m')
            exit(1)
        w2 = subprocess.run(['sudo', 'launchctl', 'kickstart', '-k', 'system/com.apple.bootpd'])
        if w2.returncode == 113 or w2.returncode == 1:
            if w2.returncode == 1:
                subprocess.run(['sudo', 'launchctl', 'bootout', 'system/com.apple.bootpd'])
            w3 = subprocess.run(['sudo', 'launchctl', 'load', '-w', '/System/Library/LaunchDaemons/bootps.plist'])
            if w3.returncode == 0:
                w2 = subprocess.run(['sudo', 'launchctl', 'kickstart', '-k', 'system/com.apple.bootpd'])
        print('---')
        if w2.returncode != 0:
            print('\033[31mFailed to restart Bootstrap service\033[0m')
            exit(1)
        print('\033[32mSuccessfully restarted Boostrap service\033[0m')
    print('Configuration saved')


def run(args):
    workers = OrderedDict()
    rootdir = os.path.dirname(os.path.dirname(os.path.realpath(__file__)))
    workerfile = os.path.join(rootdir, '.vagrant', 'workers.json')

    action_provision = glob.glob(os.path.join(rootdir, '.vagrant', 'machines', 'ubuntu*', '*', 'action_provision'))
    if not len(action_provision):
        print("\033[31mThe main virtual machine has not been configured.\033[0m")
        exit(1)
    provider = os.path.basename(os.path.dirname(action_provision[0]))

    if os.path.exists(workerfile):
        with open(workerfile) as file:
            existing_config = json.load(file, object_pairs_hook=OrderedDict)

        version = 1
        if 'version' in existing_config and type(existing_config['version']) is int:
            version = existing_config['version']

        for worker_name in (existing_config if version == 1 else existing_config['workers']):
            if len(glob.glob(os.path.join(rootdir, '.vagrant', 'machines', worker_name, '*', 'action_provision'))):
                if input("\033[93mWarning: There are existing worker machines that may conflict with new configuration.\n"
                         "They can be removed safely with 'vagrant workers destroy'.\n"
                         "Are you sure you would like to proceed without removing them? [y/N]\033[0m "
                         ).lower().strip() != 'y':
                    return
                break

        if input('Overwrite existing worker configuration? [y/N] ').lower().strip() != 'y':
            return

    ips = cast(Union[ipaddress.IPv4Network, ipaddress.IPv6Network], args.ip_range).hosts()
    if isinstance(ips, list):
        ips = iter(ips)

    gateway_ip = next(ips)
    with open(os.path.join(rootdir, '.vagrant', '.workervars'), 'w') as file:
        file.write(f"export GATEWAY_IP={gateway_ip}\n")

    for i in range(1, args.num+1):
        ip = next(ips, None)
        while (ip is not None
               and (ip.is_reserved or isinstance(ip, ipaddress.IPv4Address)
                    and str(ip).endswith('.1'))):
            ip = next(ips, None)

        if ip is None:
            raise IndexError("IP range insufficient for requested number of workers")

        data = OrderedDict()
        data['ip_addr'] = str(ip)
        if provider == 'qemu':
            data['mac_addr'] = args.mac_prefix + ":%02x:%02x:%02x" % tuple(random.randint(0, 255) for v in range(3))
        else:
            data['ssh_port'] = args.base_port + i
        workers[f'worker-{i}'] = data

    full_data = OrderedDict()
    full_data['version'] = 2
    full_data['provider'] = provider
    if provider == 'qemu':
        full_data['gateway'] = str(gateway_ip)
    full_data['workers'] = workers

    os.makedirs(os.path.dirname(workerfile), exist_ok=True)
    with open(workerfile, 'w') as file:
        json.dump(full_data, file, indent=4)

    print('Wrote new configuration to ' + workerfile)

    if provider == 'qemu':
        update_bootstrap(workers)


if __name__ == '__main__':
    run(get_args())
