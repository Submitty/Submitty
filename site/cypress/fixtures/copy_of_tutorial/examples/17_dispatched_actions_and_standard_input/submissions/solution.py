#!/usr/bin/env python3
import sys


print('Waiting on standard input...')
sys.stdout.flush()
a = input('')
print("received '{0}'".format(a))
sys.stdout.flush()