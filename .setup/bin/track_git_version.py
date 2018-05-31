#!/usr/bin/env python3

import os
import json
import subprocess

CURRENT_PATH = os.path.dirname(os.path.realpath(__file__))
CONFIG_DIR = os.path.join(CURRENT_PATH, "..", "..", "config")

if __name__ == "__main__":
  json_dir = os.path.join(CONFIG_DIR, "submitty.json")
  try:
    with open (json_dir, 'r') as infile:
      config_dict = json.load(infile)
  except:
    print("ERROR: could not locate submitty json")

  #Grab the directory of the submitty repository from the config/submitty.json
  repository_dir = config_dict['submitty_repository']

  try:
    #run the command 'git rev-parse HEAD' from the submitty repository directory
    current_commit_hash = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=repository_dir)
    print("Commit {0} is currently installed on this system.".format(current_commit_hash))
  except:
    print("ERROR: could not determine commit hash.")
    current_commit_hash = 'unknown'

  try:
    #run the command 'git describe --tag --abbrev=0' from the submitty repository directory
    current_git_tag = subprocess.check_output(['git', 'describe', '--tag', '--abbrev=0'], cwd=repository_dir)
    print("Tag {0} is the most recent git tag.".format(current_git_tag))
  except:
    print("ERROR: could not determine current git tag.")
    current_git_tag = 'unknown'


  #remove newline at the end of the hash and tag and convert them from bytes to ascii.
  current_commit_hash = current_commit_hash.decode('ascii').strip()
  current_git_tag     = current_git_tag.decode('ascii').strip()

  config_dict["installed_commit"] = current_commit_hash
  config_dict["most_recent_git_tag"] = current_git_tag

  try:
    #Update config/submitty.json to reflect the current commit hash.
    with open(json_dir, 'w') as outfile:
       json.dump(config_dict, outfile, indent=2)
  except:
    print("ERROR: could not write to {0}".format(json_dir))