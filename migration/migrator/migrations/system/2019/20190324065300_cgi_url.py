from pathlib import Path
from sqlalchemy import text
import os
import json

def up(config):
  SUBMITTY_JSON_PATH = os.path.join(config.submitty['submitty_install_dir'],'config','submitty.json')
  with open(SUBMITTY_JSON_PATH,'r') as infile:
    data = json.load(infile)

  try:
    data.pop("cgi_url")
  except Exception as e:
    pass

  with open(SUBMITTY_JSON_PATH,'w') as outfile:
    json.dump(data, outfile, indent=4)

  config.submitty = data

def down(config):
  SUBMITTY_JSON_PATH = os.path.join(config.submitty['submitty_install_dir'],'config','submitty.json')
  with open(SUBMITTY_JSON_PATH,'r') as infile:
    data = json.load(infile)

  data["cgi_url"] = '{0}/cgi-bin'.format(data['submission_url'])

  with open(SUBMITTY_JSON_PATH,'w') as outfile:
    json.dump(data, outfile, indent=4)

  config.submitty = data

