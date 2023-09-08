#!/bin/bash
VAGRANT_CLOUD_TOKEN=snLkGsKzUGacZA.atlasv1.Az8EQpaXnY1SMgoef32yHeFAirNtGj6VPeGrQS4LXho3x2ON3ftP6zmK2zfyeqY8pis
VAGRANT_USERNAME=reapernsgaming
VAGRANT_BOX=$1

VAGRANT_CLOUD_VERSION=$(curl \
  --request GET \
  --header "Authorization: Bearer $VAGRANT_CLOUD_TOKEN" \
  https://app.vagrantup.com/api/v1/box/$VAGRANT_USERNAME/$VAGRANT_BOX | \
  python3 -c \
  'import json,sys;obj=json.load(sys.stdin);version=obj["versions"][0]["version"].split(".");version[3]=str(int(version[3])+1).zfill(3);print(".".join(version))')

curl \
  --request POST \
  --header "Content-Type: application/json" \
  --header "Authorization: Bearer $VAGRANT_CLOUD_TOKEN" \
  https://app.vagrantup.com/api/v1/box/$VAGRANT_USERNAME/$VAGRANT_BOX/versions \
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
  https://app.vagrantup.com/api/v1/box/$VAGRANT_USERNAME/$VAGRANT_BOX/version/$VAGRANT_CLOUD_VERSION/providers \
  --data '
    {
      "provider": {
        "checksum": "a59e7332e8bbe896f11f478fc61fa8a6",
        "checksum_type": "md5",
        "name": "virtualbox"
      }
    }
  '

response=$(curl \
    --request GET \
    --header "Authorization: Bearer $VAGRANT_CLOUD_TOKEN" \
    https://app.vagrantup.com/api/v1/box/$VAGRANT_USERNAME/$VAGRANT_BOX/version/$VAGRANT_CLOUD_VERSION/provider/virtualbox/upload | \
    python3 -c \
    'import json,sys;obj=json.load(sys.stdin);print(obj["upload_path"])')

curl --request PUT --upload-file submitty.box "${response}"

# Release the version

curl \
  --request PUT \
  --header "Authorization: Bearer $VAGRANT_CLOUD_TOKEN" \
   https://app.vagrantup.com/api/v1/box/$VAGRANT_USERNAME/$VAGRANT_BOX/version/$VAGRANT_CLOUD_VERSION/release

