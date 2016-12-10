# -*- mode: ruby -*-
# vi: set ft=ruby :

$script = <<SCRIPT
GIT_PATH=/usr/local/submitty/GIT_CHECKOUT_Submitty
chmod +x ${GIT_PATH}/.setup/vagrant.sh
chmod +x ${GIT_PATH}/.setup/bin/*
cd ${GIT_PATH}
${GIT_PATH}/.setup/bin/reset_system.py
${GIT_PATH}/.setup/vagrant.sh vagrant
SCRIPT

Vagrant.configure(2) do |config|
    # Ubuntu 14.04 (Trusty Tahr) - 64bit
    config.vm.box = "ubuntu/trusty64"

    config.vm.network "private_network", ip: "192.168.56.101", auto_config: false

    config.vm.provider "virtualbox" do |vb|
      #vb.gui = true

      vb.memory = 2048
      vb.cpus = 2
      # When you put your computer (while running the VM) to sleep, then resume work some time later the VM will be out
      # of sync timewise with the host for however long the host was asleep. Of course, the VM by default will
      # detect this and if the drift is great enough, it'll resync things such that the time matches, otherwise
      # the VM will just slowly adjust the timing so they'll eventually match. However, this can cause confusion when
      # times are important for late day calculations and building so we set the maximum time the VM and host can drift
      # to be 10 seconds at most which should make things work generally ideally
      vb.customize [ "guestproperty", "set", :id, "/VirtualBox/GuestAdd/VBoxService/--timesync-set-threshold", 10000 ]
    end

    config.vm.synced_folder ".", "/usr/local/submitty/GIT_CHECKOUT_Submitty", create: true, owner: "vagrant", group: "vagrant", mount_options: ["dmode=777", "fmode=777"]

    config.vm.provision "shell", inline: $script

    config.vm.network "forwarded_port", guest: 5432, host: 15432
end
