#!/usr/bin/env python3
import socket
import os
import sys
import time
import json
import traceback
import threading
import argparse

MY_NAME = ""
KNOWN_HOSTS_JSON = 'knownhosts.json'
ADDRESS_BOOK = dict()

#knownhosts_tcp.csv and knownhosts_udp.csv are of the form
#sender,recipient,port_number
# such that sender sends all communications to recipient via port_number. 
def read_known_hosts_json():
  global ADDRESS_BOOK, MY_NAME
  with open(KNOWN_HOSTS_JSON) as infile:
    content = json.load(infile)
  
  for host, info in content['hosts'].items():
    tcp_port = info['tcp_start_port']
    udp_port = info['udp_start_port']
    
    if host == MY_NAME:
      continue
    else:
      ADDRESS_BOOK[host] = {'udp_port' : udp_port, 'tcp_port' : tcp_port }


def main():
  read_known_hosts_json()

  stdin = input('')
  parts = stdin.split('-')
  next_host = parts[0]
  next_message = ':'.join(parts[1:])
  next_message += ':FINISHED!'
  next_message = next_message.encode('utf-8')
  
  # First, try udp:
  try:
    outgoing_socket = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    addr = (next_host, ADDRESS_BOOK[next_host]['udp_port'])
    outgoing_socket.sendto(next_message, addr)
  except Exception as e:
    pass

  #Then, try tcp
  try:
    outgoing_socket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    outgoing_socket.connect((next_host, ADDRESS_BOOK[next_host]['tcp_port']))
    outgoing_socket.sendall(next_message)
  except Exception as e:
    pass

if __name__ == '__main__':
  MY_NAME = socket.gethostname()
  try:
      main()
  except KeyboardInterrupt:
    sys.exit(0)


