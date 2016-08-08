# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.configure(2) do |config|

  # Ubuntu 14.04 (Trusty Tahr) - 64bit
  config.vm.box = "ubuntu/trusty64"

  config.vm.network "private_network", ip: "192.168.56.101", auto_config: false

  config.vm.provider "virtualbox" do |v|
    v.memory = 2048
    v.cpus = 2
  end

  config.vm.synced_folder ".", "/usr/local/submitty/GIT_CHECKOUT_Submitty", create: true, owner: "vagrant", group: "vagrant", mount_options: ["dmode=777", "fmode=777"]

  config.vm.provision "shell" do |s|
    s.path = ".setup/vagrant/reset_system.py"
  end

  config.vm.provision "shell" do |s|
    s.path = ".setup/vagrant.sh"
    s.args = ["vagrant"]
  end

  config.vm.network "forwarded_port", guest: 5432, host: 15432
end
