#!/usr/bin/env bash

# This sets up the Vagrant VM to be used as the Travis box for testing

chmod -x /etc/update-motd.d/*
chmod -x /usr/share/landscape/landscape-sysinfo.wrapper
chmod +x /etc/update-motd.d/00-header

echo -e '
.___________..______          ___   ____    ____  __       _______.
|           ||   _  \        /   \  \   \  /   / |  |     /       |
`---|  |----`|  |_)  |      /  ^  \  \   \/   /  |  |    |   (----`
    |  |     |      /      /  /_\  \  \      /   |  |     \   \
    |  |     |  |\  \----./  _____  \  \    /    |  | .----)   |
    |__|     | _| `._____/__/     \__\  \__/     |__| |_______/

' > /etc/motd
chmod +rx /etc/motd

travis_password=`perl -e 'printf("%s\n", crypt("travis", "password"))'`
useradd -m -p travis_password -s /bin/bash travis


apt-get -y install git-core curl zlib1g-dev build-essential libssl-dev libreadline-dev libyaml-dev libsqlite3-dev \
sqlite3 libxml2-dev libxslt1-dev libcurl4-openssl-dev python-software-properties libffi-dev rbenv

# We use rbenv to install Ruby for the travis user to be a newer version than that comes with Ubuntu 14.04
sudo -u travis bash << EOF
pushd /home/travis
rbenv install -v 2.3.1
rbenv global 2.3.1
EOF
echo 'export PATH="$HOME/.rbenv/bin:$PATH"' >> /home/travis/.bash_profile
source /home/travis/.bash_profile