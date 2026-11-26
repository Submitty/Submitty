#!/usr/bin/env bash
# Upgrade packages and reboot so guest additions build against the newest kernel.
export DEBIAN_FRONTEND=noninteractive

apt-get update
apt-get -y -o Dpkg::Options::="--force-confold" dist-upgrade

reboot
