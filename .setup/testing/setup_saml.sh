#!/usr/bin/env bash

set -ev

docker run -p 7000:8080 --add-host host.docker.internal:host-gateway \
 -d submitty/docker-test-saml-idp

mkdir -p /usr/local/submitty/config/saml/certs

openssl req -x509 -sha256 -days 365 -nodes -newkey rsa:4096 \
 -keyout /usr/local/submitty/config/saml/certs/sp.key \
 -out /usr/local/submitty/config/saml/certs/sp.crt --subj "/C=US"

curl http://localhost:7000/simplesaml/saml2/idp/metadata.php \
 --output /usr/local/submitty/config/saml/idp_metadata.xml

chown -R submitty_php:submitty_php /usr/local/submitty/config/saml

/usr/local/submitty/sbin/saml_utils.php --add_users

sed -i -e 's/"name": ""/"name": "SAML"/g' /usr/local/submitty/config/authentication.json
sed -i -e 's/"username_attribute": ""/"username_attribute": "uid"/g' /usr/local/submitty/config/authentication.json
