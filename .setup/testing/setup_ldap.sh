#!/usr/bin/env bash

set -ev

CUR_DIR="$( cd -P "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

source "$CUR_DIR/../vagrant/setup_ldap.sh"

sed -i -e 's/"url": ""/"url": "ldap:\/\/localhost"/g' /usr/local/submitty/config/authentication.json
sed -i -e 's/"uid": ""/"uid": "uid"/g' /usr/local/submitty/config/authentication.json
sed -i -e 's/"bind_dn": ""/"bind_dn": "ou=users,dc=vagrant,dc=local"/g' /usr/local/submitty/config/authentication.json
