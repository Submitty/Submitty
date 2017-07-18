# -*- mode: ruby -*-
# vi: set ft=ruby :

$script = <<SCRIPT
GIT_PATH=/usr/local/submitty/GIT_CHECKOUT_Submitty
mkdir -p ${GIT_PATH}/.vagrant/logs
${GIT_PATH}/.setup/vagrant.sh vagrant 2>&1 | tee ${GIT_PATH}/.vagrant/logs/vagrant.log
SCRIPT

Vagrant.configure(2) do |config|
  # Specify the various machines that we might develop on. After defining a name, we
  # can specify if the vm is our "primary" one (if we don't specify a VM, it'll use
  # that one) as well as making sure all non-primary ones have "autostart: false" set
  # so that when we do "vagrant up", it doesn't spin up those machines.

  # Our primary development target, this is what RPI runs Submitty on
  config.vm.define 'ubuntu', primary: true do |ubuntu|
    ubuntu.vm.box = 'bento/ubuntu-16.04'
    ubuntu.vm.network 'forwarded_port', guest: 80, host: 8080
    ubuntu.vm.network 'forwarded_port', guest: 5432, host: 15432
  end

  config.vm.define 'debian', autostart: false do |debian|
    debian.vm.box = 'debian/jessie64'
    debian.vm.network 'forwarded_port', guest: 80, host: 8888
    debian.vm.network 'forwarded_port', guest: 5432, host: 25432
  end

  config.vm.provider 'virtualbox' do |vb|
    #vb.gui = true

    vb.memory = 2048
    vb.cpus = 2
    # When you put your computer (while running the VM) to sleep, then resume work some time later the VM will be out
    # of sync timewise with the host for however long the host was asleep. Of course, the VM by default will
    # detect this and if the drift is great enough, it'll resync things such that the time matches, otherwise
    # the VM will just slowly adjust the timing so they'll eventually match. However, this can cause confusion when
    # times are important for late day calculations and building so we set the maximum time the VM and host can drift
    # to be 10 seconds at most which should make things work generally ideally
    vb.customize ['guestproperty', 'set', :id, '/VirtualBox/GuestAdd/VBoxService/--timesync-set-threshold', 10000 ]
  end

  config.vm.synced_folder '.', '/usr/local/submitty/GIT_CHECKOUT_Submitty', create: true, mount_options: ["dmode=775", "fmode=774"]

  config.vm.provision 'shell', inline: $script
end
