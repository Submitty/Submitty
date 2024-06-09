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


def get_args(m_series = False):
    parser = argparse.ArgumentParser(
        description='Script to generate configuration for '
        'development worker machines')

    parser.add_argument('-n', '--num', default=1, type=int,
                        help='Number of worker machines to configure')
    parser.add_argument('--ip-range', default='192.168.56.0/24', type=ipaddress.ip_network,
                        help='IP address range for workers')
    parser.add_argument('--base-port', default=2240, type=int,
                        help='Base ssh port (ports will be assigned incrementally)')
    if m_series:
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
                 )[0].lower() != 'y':
            return

    if os.path.isfile(workerfile):
        if input('Overwrite existing worker configuration? [y/N] ')[0].lower() != 'y':
            return

    ips = cast(Union[ipaddress.IPv4Network, ipaddress.IPv6Network], args.ip_range).hosts()
    if isinstance(ips, list):
        ips = iter(ips)

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
        if m_series:
            data['mac_addr'] = args.mac_prefix + ":%02x:%02x:%02x" % tuple(random.randint(0, 255) for v in range(3))
        else:
            data['ssh_port'] = args.base_port + i
        workers[f'worker-{i}'] = data

    os.makedirs(os.path.dirname(workerfile), exist_ok=True)
    with open(workerfile, 'w') as file:
        json.dump(workers, file, indent=4)

    print('Wrote new configuration to ' + workerfile)

    if m_series:
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
