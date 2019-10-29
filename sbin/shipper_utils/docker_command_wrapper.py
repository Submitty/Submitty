import argparse
import docker
import os
import sys
import traceback

def parse_arguments():
  #parse arguments
  parser = argparse.ArgumentParser(description='A wrapper for the various systemctl functions. \
    This script must be run as the submitty supervisor.',
    epilog="""ERROR CODES:
0 = success
1 = failure
""")
  parser.add_argument("image", metavar="IMAGE", type=str, help="The image to pull")
  return parser.parse_args()

if __name__ == '__main__':
  args = parse_arguments()

  client = docker.client.from_env()

  try:
    repo, tag = args.image.split(':')
    client.images.pull(repository=repo, tag=tag)
  except Exception as e:
    traceback.print_exc()
    sys.exit(1)

  sys.exit(0)