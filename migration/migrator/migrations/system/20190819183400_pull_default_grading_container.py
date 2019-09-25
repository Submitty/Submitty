import os
import json
from pathlib import Path
import docker

def up(config):

    client = docker.from_env()
    client.images.pull('submitty/autograding-default', tag='latest')


def down(config):
    pass
