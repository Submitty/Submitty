#!/usr/bin/env bash

# This sets up the Vagrant VM to be used as the Travis box for testing

chmod -x /etc/update-motd.d/*
chmod -x /usr/share/landscape/landscape-sysinfo.wrapper
chmod +x /etc/update-motd.d/00-header

echo -e '
.___________..______       ___   ____    ____  __     _____.
|           ||   _  \     /   \  \   \  /   / |  |   /     |
`---|  |----`|  |_)  |   /  ^  \  \   \/   /  |  |  |   (--`
    |  |     |      /   /  /_\  \  \      /   |  |   \   \
    |  |     |  |\  \-./  _____  \  \    /    |  | .--)   |
    |__|     | _| `.__/__/     \__\  \__/     |__| |_____/

' > /etc/motd
chmod +rx /etc/motd

useradd -m -s /bin/bash travis
echo "travis:travis" | sudo chpasswd travis
echo "travis ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/travis
mkdir /home/travis/.ssh
cp /home/vagrant/.ssh/authorized_keys /home/travis/.ssh/authorized_keys
chown travis:travis /home/travis/.ssh/authorized_keys
chmod 600 /home/travis/.ssh/authorized_keys

apt-get -y install git-core curl zlib1g-dev build-essential libssl-dev libreadline-dev libyaml-dev libsqlite3-dev \
sqlite3 libxml2-dev libxslt1-dev libcurl4-openssl-dev python-software-properties libffi-dev

# We use rbenv to install Ruby for the travis user to be a newer version than that comes with Ubuntu 14.04
sudo -u travis bash << EOF
pushd /home/travis
git clone https://github.com/rbenv/rbenv.git ~/.rbenv
cd ~/.rbenv && src/configure && make -C src
echo 'export PATH="$HOME/.rbenv/bin:$PATH"' >> ~/.bash_profile
~/.rbenv/bin/rbenv init
git clone https://github.com/rbenv/ruby-build.git ~/.rbenv/plugins/ruby-build
source ~/.bash_profile
rbenv install -v 2.3.1
rbenv global 2.3.1
echo 'export PATH="$HOME/.rbenv/bin:$PATH"' >> /home/travis/.bash_profile
source /home/travis/.bash_profile
popd
EOF