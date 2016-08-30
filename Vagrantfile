# -*- mode: ruby -*-
# vi: set ft=ruby :

VAGRANT_COMMAND = ARGV[0]

Vagrant.configure(2) do |config|

  # This is the base VM that should be used for development of the Submitty application. Just typing "vagrant up",
  # "vagrant ssh", etc. will always work route us to this box.
  config.vm.define "submitty", primary: true do |submitty|

    # Ubuntu 14.04 (Trusty Tahr) - 64bit
    submitty.vm.box = "ubuntu/trusty64"

    submitty.vm.network "private_network", ip: "192.168.56.101", auto_config: false

    submitty.vm.provider "virtualbox" do |vb|
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

    submitty.vm.synced_folder ".", "/usr/local/submitty/GIT_CHECKOUT_Submitty", create: true, owner: "vagrant", group: "vagrant", mount_options: ["dmode=777", "fmode=777"]

    submitty.vm.provision "shell" do |s|
      s.path = ".setup/vagrant/reset_system.py"
    end

    submitty.vm.provision "shell" do |s|
      s.path = ".setup/vagrant.sh"
      s.args = ["vagrant"]
    end

    submitty.vm.network "forwarded_port", guest: 5432, host: 15432
  end

  # This VM should only be used when attempting to modify the Travis CI build. It spins up a VM that is
  # as close to the Travis VM image as I could make it (with only the necessary languages we have).
  # This is so that we don't have to make hundreds of "travis" commits trying to debug something
  config.vm.define "travis", autostart: false do |travis|
    travis.vm.box = "ubuntu/trusty64"

    travis.vm.provision "shell" do |s|
      s.path = ".setup/travis/vagrant.sh"
    end

    # We want to be logged in as the "travis" user by default to better mimic how we might
    # interact/debug the travis build
    if VAGRANT_COMMAND == "ssh"
      config.ssh.username = 'travis'
    end
  end
end
