#!/usr/bin/python3

import argparse
import ipaddress
import json
import os
from collections import OrderedDict


def get_args():
    parser = argparse.ArgumentParser(
        description='Script to generate configuration for '
        'development worker machines')

    parser.add_argument('-n', '--num', default=1, type=int,
                        help='Number of worker machines to configure')
    parser.add_argument('--ip-range', default='192.168.56.0/24', type=ipaddress.ip_network,
                        help='IP address range for workers')
    parser.add_argument('--base-port', default=2240, type=int,
                        help='Base ssh port (ports will be assigned incrementally)')

    return parser.parse_args()


def main():
    args = get_args()
    workers = OrderedDict()
    workerfile = os.path.join(os.path.dirname(os.path.realpath(__file__)), '.vagrant', 'workers.json')

    if os.path.isfile(workerfile):
        if input('Overwrite existing worker configuration? [y/N] ').lower() != 'y':
            return

    ips = args.ip_range.hosts()
    if isinstance(ips, list):
        ips = iter(ips)
    for i in range(1, args.num+1):
        ip = next(ips, None)
        if ip is None:
            raise IndexError("IP range insufficient for requested number of workers")
        data = OrderedDict()
        data['ip_addr'] = str(ip)
        data['base_port'] = args.base_port + i
        workers[f'worker-{i}'] = data

    with open(workerfile, 'w') as file:
        json.dump(workers, file, indent=4)

    print('Wrote new configuration to ' + workerfile)


if __name__ == '__main__':
    main()
