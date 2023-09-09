#!/bin/bash
VAGRANT_CLOUD_TOKEN=$1
VAGRANT_USERNAME=$2
VAGRANT_BOX=$3

VAGRANT_CLOUD_VERSION=$(curl \
  --request GET \
  --header "Authorization: Bearer $VAGRANT_CLOUD_TOKEN" \
  https://app.vagrantup.com/api/v1/box/"$VAGRANT_USERNAME"/"$VAGRANT_BOX" | \
  python3 -c \
  'import json,sys;obj=json.load(sys.stdin);version=obj["versions"][0]["version"].split(".");version[3]=str(int(version[3])+1).zfill(3);print(".".join(version))')

curl \
  --request POST \
  --header "Content-Type: application/json" \
  --header "Authorization: Bearer $VAGRANT_CLOUD_TOKEN" \
  https://app.vagrantup.com/api/v1/box/"$VAGRANT_USERNAME"/"$VAGRANT_BOX"/versions \
  --data '
    {
      "version": {
        "version": "'"$VAGRANT_CLOUD_VERSION"'",
        "description": "'"$VAGRANT_BOX version $VAGRANT_CLOUD_VERSION"'"
      }
    }
  '

curl \
  --request POST \
  --header "Content-Type: application/json" \
  --header "Authorization: Bearer $VAGRANT_CLOUD_TOKEN" \
  https://app.vagrantup.com/api/v1/box/"$VAGRANT_USERNAME"/"$VAGRANT_BOX"/version/"$VAGRANT_CLOUD_VERSION"/providers \
  --data '
    {
      "provider": {
        "name": "virtualbox"
      }
    }
  '

response=$(curl \
    --request GET \
    --header "Authorization: Bearer $VAGRANT_CLOUD_TOKEN" \
    https://app.vagrantup.com/api/v1/box/"$VAGRANT_USERNAME"/"$VAGRANT_BOX"/version/"$VAGRANT_CLOUD_VERSION"/provider/virtualbox/upload | \
    python3 -c \
    'import json,sys;obj=json.load(sys.stdin);print(obj["upload_path"])')

curl --request PUT --upload-file "$VAGRANT_BOX".box "${response}"

# Release the version

curl \
  --request PUT \
  --header "Authorization: Bearer $VAGRANT_CLOUD_TOKEN" \
   https://app.vagrantup.com/api/v1/box/"$VAGRANT_USERNAME"/"$VAGRANT_BOX"/version/"$VAGRANT_CLOUD_VERSION"/release