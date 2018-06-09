# -*- mode: ruby -*-
# vi: set ft=ruby :

# If you want to install extra packages (such as rpi or matlab), you need to have the environment
# variable EXTRA set. The easiest way to do this is doing:
#
# EXTRA=rpi vagrant up
#   or
# EXTRA=rpi,matlab vagrant up

$script = <<SCRIPT
GIT_PATH=/usr/local/submitty/GIT_CHECKOUT/Submitty
DISTRO=$(lsb_release -i | sed -e "s/Distributor\ ID\:\t//g" | tr '[:upper:]' '[:lower:]')
mkdir -p ${GIT_PATH}/.vagrant/${DISTRO}/logs
${GIT_PATH}/.setup/vagrant/setup_vagrant.sh #{ENV['EXTRA']} 2>&1 | tee ${GIT_PATH}/.vagrant/${DISTRO}/logs/vagrant.log
SCRIPT

unless Vagrant.has_plugin?('vagrant-vbguest')
  raise 'vagrant-vbguest is not installed!'
end

Vagrant.configure(2) do |config|
  # Specify the various machines that we might develop on. After defining a name, we
  # can specify if the vm is our "primary" one (if we don't specify a VM, it'll use
  # that one) as well as making sure all non-primary ones have "autostart: false" set
  # so that when we do "vagrant up", it doesn't spin up those machines.

  # Our primary development target, this is what RPI runs Submitty on
  config.vm.define 'ubuntu', primary: true do |ubuntu|
    ubuntu.vm.box = 'bento/ubuntu-16.04'
    ubuntu.vm.network 'forwarded_port', guest: 5432, host: 15432
    ubuntu.vm.network 'private_network', ip: '192.168.56.101'
    ubuntu.vm.network 'private_network', ip: '192.168.56.102'
  end

  config.vm.define 'debian', autostart: false do |debian|
    debian.vm.box = 'bento/debian-8.8'
    debian.vm.network 'forwarded_port', guest: 5432, host: 25432
    debian.vm.network 'private_network', ip: '192.168.56.201'
    debian.vm.network 'private_network', ip: '192.168.56.202'
  end

  config.vm.provider 'virtualbox' do |vb|

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

  config.vm.provision :shell, :inline => " sudo timedatectl set-timezone America/New_York", run: "once"

  config.vm.synced_folder '.', '/usr/local/submitty/GIT_CHECKOUT/Submitty', create: true, mount_options: ["dmode=775", "fmode=774"]

  if File.directory?(File.expand_path("../AnalysisTools"))
    config.vm.synced_folder "../AnalysisTools","/usr/local/submitty/GIT_CHECKOUT/AnalysisTools", mount_options: ["dmode=775", "fmode=774"]
  end
  if File.directory?(File.expand_path("../Tutorial"))
    config.vm.synced_folder "../Tutorial","/usr/local/submitty/GIT_CHECKOUT/Tutorial", mount_options: ["dmode=775", "fmode=774"]
  end
  if File.directory?(File.expand_path("../Lichen"))
    config.vm.synced_folder "../Lichen","/usr/local/submitty/GIT_CHECKOUT/Lichen", mount_options: ["dmode=775", "fmode=774"]
  end

  if File.directory?(File.expand_path("../GIT_ANALYSIS_TOOLS"))
    config.vm.synced_folder "../GIT_ANALYSIS_TOOLS","/usr/local/submitty/GIT_CHECKOUT_AnalysisTools", mount_options: ["dmode=775", "fmode=774"]
  end
  if File.directory?(File.expand_path("../GIT_TUTORIAL"))
    config.vm.synced_folder "../GIT_TUTORIAL","/usr/local/submitty/GIT_CHECKOUT_Tutorial", mount_options: ["dmode=775", "fmode=774"]
  end
  if File.directory?(File.expand_path("../GIT_LICHEN"))
    config.vm.synced_folder "../GIT_LICHEN","/usr/local/submitty/GIT_CHECKOUT_Lichen", mount_options: ["dmode=775", "fmode=774"]
  end

  config.vm.provision 'shell', inline: $script

  if ARGV.include?('ssh')
    config.ssh.username = 'root'
    config.ssh.password = 'vagrant'
    config.ssh.insert_key = 'true'
  end
end
