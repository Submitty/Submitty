#!/usr/bin/env bash
# Install Vagrant's insecure public key; Vagrant swaps it for a generated key on first `vagrant up`.
HOME_DIR=/home/vagrant

mkdir -p "$HOME_DIR/.ssh"
curl -fsSL https://raw.githubusercontent.com/hashicorp/vagrant/main/keys/vagrant.pub \
  -o "$HOME_DIR/.ssh/authorized_keys"
chmod 700 "$HOME_DIR/.ssh"
chmod 600 "$HOME_DIR/.ssh/authorized_keys"
chown -R vagrant:vagrant "$HOME_DIR/.ssh"

# Speeds up `vagrant ssh` when the host has no reverse DNS.
echo 'UseDNS no' > /etc/ssh/sshd_config.d/99-vagrant.conf
