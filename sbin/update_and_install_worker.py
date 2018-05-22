#!/usr/bin/env python3

import os
import sys

if __name__ == "__main__":
  # if len(sys.args) != 1:
  #   print("ERROR, we need a tag id.")
  #the most recent master commit on primary submitty.
  # primary_commit_number = 


  # git fetch origin master
  # git merge primary_commit_number

  exit_code = os.system('/usr/local/submitty/.setup/INSTALL_SUBMITTY.sh clean')
  sys.exit(exit_code)

  #Check that we are at least greater than the tag.
