#!/usr/bin/env bash
# Shrink the image before export.
export DEBIAN_FRONTEND=noninteractive

apt-get -y autoremove --purge
apt-get -y clean
rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

journalctl --rotate
journalctl --vacuum-time=1s
find /var/log -type f -exec truncate -s 0 {} \;

# Regenerated on next boot so every VM made from the box gets its own ID.
truncate -s 0 /etc/machine-id
rm -f /var/lib/dbus/machine-id

rm -f /home/vagrant/.bash_history /root/.bash_history

# The disk is attached with discard enabled, so this releases freed blocks from the VDI.
fstrim -av
sync
