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
DISTRO=$(lsb_release -si | tr '[:upper:]' '[:lower:]')
VERSION=$(lsb_release -sc | tr '[:upper:]' '[:lower:]')
mkdir -p ${GIT_PATH}/.vagrant/${DISTRO}/${VERSION}/logs
bash ${GIT_PATH}/.setup/vagrant/setup_vagrant.sh #{ENV['EXTRA']} 2>&1 | tee ${GIT_PATH}/.vagrant/${DISTRO}/${VERSION}/logs/vagrant.log
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

  # ideally we would use hwcron or something as the owner/group, but since that user doesn't exist
  # till post-provision (and this is mounted before provisioning), we want the group to be 'vagrant'
  # which is guaranteed to exist and that during install_system.sh we add hwcron/hwphp/etc to the
  # vagrant group so that they can write to this shared folder, primarily just for the log files
  owner = 'root'
  group = 'vagrant'
  mount_options = %w(dmode=775 fmode=664)
  config.vm.synced_folder '.', '/usr/local/submitty/GIT_CHECKOUT/Submitty', create: true, owner: owner, group: group, mount_options: mount_options

  optional_repos = %w(AnalysisTools Lichen RainbowGrades Tutorial)
  optional_repos.each {|repo|
    repo_path = File.expand_path("../" + repo)
    if File.directory?(repo_path)
      config.vm.synced_folder repo_path, "/usr/local/submitty/GIT_CHECKOUT/" + repo, owner: owner, group: group, mount_options: mount_options
    end
  }

  config.vm.provision 'shell', inline: $script

  if ARGV.include?('ssh')
    config.ssh.username = 'root'
    config.ssh.password = 'vagrant'
    config.ssh.insert_key = 'true'
  end
end
