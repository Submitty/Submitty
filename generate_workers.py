#!/usr/bin/python3

import argparse
import glob
import ipaddress
import json
import os
import random
import platform
import subprocess
from collections import OrderedDict
from typing import Union, cast
from tempfile import TemporaryDirectory


def get_args(m_series=False):
    parser = argparse.ArgumentParser(
        description='Script to generate configuration for '
        'development worker machines', prog='vagrant workers generate')

    default_provider = 'virtualbox'
    if m_series:
        default_provider = 'qemu'

    parser.add_argument('-n', '--num', default=1, type=int,
                        help='Number of worker machines to configure')
    parser.add_argument('--provider', default=default_provider, type=str,
                        help='The VM provider (default on this machine is ' + default_provider + ')')
    parser.add_argument('--ip-range', default='192.168.56.0/24', type=ipaddress.ip_network,
                        help='IP address range for workers')
    parser.add_argument('--base-port', default=2240, type=int,
                        help='Base ssh port (ports will be assigned incrementally)')
    parser.add_argument('--mac-prefix', default='52:54:00', type=str,
                        help='MAC address prefix for workers (QEMU only)')

    return parser.parse_args()


def main():
    m_series = platform.processor() == 'arm' and platform.system() == 'Darwin'
    args = get_args(m_series)
    workers = OrderedDict()
    rootdir = os.path.dirname(os.path.realpath(__file__))
    workerfile = os.path.join(rootdir, '.vagrant', 'workers.json')

    if len(glob.glob(os.path.join(rootdir, '.vagrant', 'machines', '*', '*', 'action_provision'))):
        if input("Warning: There are existing vagrant machines in this project that may conflict"
                 " with new configuration. Are you sure you would like to proceed? [y/N] "
                 ).lower().strip() != 'y':
            return

    if os.path.isfile(workerfile):
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
        if args.provider == 'qemu':
            data['mac_addr'] = args.mac_prefix + ":%02x:%02x:%02x" % tuple(random.randint(0, 255) for v in range(3))
        else:
            data['ssh_port'] = args.base_port + i
        workers[f'worker-{i}'] = data

    full_data = OrderedDict()
    full_data['version'] = 2
    full_data['provider'] = args.provider
    if args.provider == 'qemu':
        full_data['gateway'] = str(gateway_ip)
    full_data['workers'] = workers

    os.makedirs(os.path.dirname(workerfile), exist_ok=True)
    with open(workerfile, 'w') as file:
        json.dump(full_data, file, indent=4)

    print('Wrote new configuration to ' + workerfile)

    if args.provider == 'qemu':
        print('Updating Bootstrap configuration...')
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
                print('Failed to save configuration')
                exit(1)
            w2 = subprocess.run(['sudo', 'launchctl', 'kickstart', '-k', 'system/com.apple.bootpd'])
            if w2.returncode != 0:
                print('Failed to restart Bootstrap service')
                exit(1)
        print('Configuration saved')


if __name__ == '__main__':
    main()
