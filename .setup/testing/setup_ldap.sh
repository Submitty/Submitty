#!/usr/bin/env bash

set -ev

echo 'slapd/root_password password password' | debconf-set-selections &&\
    echo 'slapd/root_password_again password password' | debconf-set-selections && \
    DEBIAN_FRONTEND=noninteractive apt-get install -qqy slapd ldap-utils
echo "slapd slapd/no_configuration boolean false" | debconf-set-selections
echo "slapd slapd/domain string vagrant.local" | debconf-set-selections
echo "slapd shared/organization string 'Vagrant LDAP'" | debconf-set-selections
echo "slapd slapd/password1 password root_password" | debconf-set-selections
echo "slapd slapd/password2 password root_password" | debconf-set-selections
echo "slapd slapd/backend select HDB" | debconf-set-selections
echo "slapd slapd/purge_database boolean true" | debconf-set-selections
echo "slapd slapd/allow_ldap_v2 boolean false" | debconf-set-selections
echo "slapd slapd/move_old_database boolean true" | debconf-set-selections
dpkg-reconfigure -f noninteractive slapd
echo "" >> /etc/ldap/ldap.conf
echo "BASE   dc=vagrant,dc=local" >> /etc/ldap/ldap.conf
echo "URI    ldap://localhost" >> /etc/ldap/ldap.conf

echo -e "dn: ou=users,dc=vagrant,dc=local
objectClass: organizationalUnit
objectClass: top
ou: users" > /tmp/base.ldif
ldapadd -x -w root_password -D "cn=admin,dc=vagrant,dc=local" -f /tmp/base.ldif
rm -f /tmp/base.ldif

sed -i -e 's/"url": ""/"url": "ldap:\/\/localhost"/g' /usr/local/submitty/config/authentication.json
sed -i -e 's/"uid": ""/"uid": "uid"/g' /usr/local/submitty/config/authentication.json
sed -i -e 's/"bind_dn": ""/"bind_dn": "ou=users,dc=vagrant,dc=local"/g' /usr/local/submitty/config/authentication.json
