# -*- mode: ruby -*-
# vi: set ft=ruby :

# Usage:
#   vagrant up
#       or
#   NO_SUBMISSIONS=1 vagrant up
#       or
#   EXTRA=rpi vagrant up
#       or
#   WORKER_PAIR=1 vagrant up
#
#
# If you want to install extra packages (such as rpi or matlab), you need to have the environment
# variable EXTRA set. The easiest way to do this is doing:
#
# EXTRA=rpi vagrant up
#   or
# EXTRA=rpi,matlab vagrant up
#
# If you don't want any submissions to be automatically generated for the courses created
# by vagrant, you'll want to specify NO_SUBMISSIONS flag.

# Don't buffer output.
$stdout.sync = true
$stderr.sync = true

extra_command = ''
autostart_worker = false
if ENV.has_key?('NO_SUBMISSIONS')
    extra_command << '--no_submissions '
end
if ENV.has_key?('EXTRA')
    extra_command << ENV['EXTRA']
end
if ENV.has_key?('WORKER_PAIR')
    autostart_worker = true
    extra_command << '--worker-pair '
end

$script = <<SCRIPT
GIT_PATH=/usr/local/submitty/GIT_CHECKOUT/Submitty
DISTRO=$(lsb_release -si | tr '[:upper:]' '[:lower:]')
VERSION=$(lsb_release -sr | tr '[:upper:]' '[:lower:]')
bash ${GIT_PATH}/.setup/vagrant/setup_vagrant.sh #{extra_command} 2>&1 | tee ${GIT_PATH}/.vagrant/install_${DISTRO}_${VERSION}.log
SCRIPT

$worker_script = <<SCRIPT
GIT_PATH=/usr/local/submitty/GIT_CHECKOUT/Submitty
DISTRO=$(lsb_release -si | tr '[:upper:]' '[:lower:]')
VERSION=$(lsb_release -sr | tr '[:upper:]' '[:lower:]')
bash ${GIT_PATH}/.setup/install_worker.sh #{extra_command} 2>&1 | tee ${GIT_PATH}/.vagrant/install_worker.log
SCRIPT

# Bento does not yet make available ARM64 compatible boxes, so we need to use an alternative box that's built from bento's sources
ubuntu_2004_box = if Vagrant::Util::Platform.darwin? && (`uname -m`.chomp == "arm64" || (`sysctl -n machdep.cpu.brand_string`.chomp.include? 'M1'))
  'jeffnoxon/ubuntu-20.04-arm64'
else
  'bento/ubuntu-20.04'
end

def mount_folders(config, mount_options)
  # ideally we would use submitty_daemon or something as the owner/group, but since that user doesn't exist
  # till post-provision (and this is mounted before provisioning), we want the group to be 'vagrant'
  # which is guaranteed to exist and that during install_system.sh we add submitty_daemon/submitty_php/etc to the
  # vagrant group so that they can write to this shared folder, primarily just for the log files
  owner = 'root'
  group = 'vagrant'
  config.vm.synced_folder '.', '/usr/local/submitty/GIT_CHECKOUT/Submitty', create: true, owner: owner, group: group, mount_options: mount_options

  optional_repos = %w(AnalysisTools Lichen RainbowGrades Tutorial CrashCourseCPPSyntax LichenTestData)
  optional_repos.each {|repo|
    repo_path = File.expand_path("../" + repo)
    if File.directory?(repo_path)
      config.vm.synced_folder repo_path, "/usr/local/submitty/GIT_CHECKOUT/" + repo, owner: owner, group: group, mount_options: mount_options
    end
  }
end

Vagrant.configure(2) do |config|
  mount_options = []

  # Specify the various machines that we might develop on. After defining a name, we
  # can specify if the vm is our "primary" one (if we don't specify a VM, it'll use
  # that one) as well as making sure all non-primary ones have "autostart: false" set
  # so that when we do "vagrant up", it doesn't spin up those machines.

  config.vm.define 'submitty-worker', autostart: autostart_worker do |ubuntu|
    ubuntu.vm.box = ubuntu_2004_box
    # If this IP address changes, it must be changed in install_system.sh and
    # CONFIGURE_SUBMITTY.py to allow the ssh connection
    ubuntu.vm.network "private_network", ip: "172.18.2.8"
    ubuntu.vm.network 'forwarded_port', guest: 22, host: 2220, id: 'ssh'
    ubuntu.vm.provision 'shell', inline: $worker_script
  end

  # Our primary development target, RPI uses it as of Fall 2021
  config.vm.define 'ubuntu-20.04', primary: true do |ubuntu|
    ubuntu.vm.box = ubuntu_2004_box
    ubuntu.vm.network 'forwarded_port', guest: 1511, host: 1511   # site
    ubuntu.vm.network 'forwarded_port', guest: 8443, host: 8443   # Websockets
    ubuntu.vm.network 'forwarded_port', guest: 5432, host: 16442  # database
    ubuntu.vm.network 'forwarded_port', guest: 22, host: 2222, id: 'ssh'
    ubuntu.vm.provision 'shell', inline: $script
  end

  # Deprecated: This will be removed in the future and should not be relied upon.
  # Note: This box is not compatible with macos M1
  config.vm.define 'ubuntu-18.04', autostart: false do |ubuntu|
    ubuntu.vm.box = 'bento/ubuntu-18.04'
    ubuntu.vm.network 'forwarded_port', guest: 1501, host: 1501   # site
    ubuntu.vm.network 'forwarded_port', guest: 8433, host: 8433   # Websockets
    ubuntu.vm.network 'forwarded_port', guest: 5432, host: 16432  # database
    ubuntu.vm.provision 'shell', inline: $script
  end

  config.vm.provider 'virtualbox' do |vb, override|
    vb.memory = 2048
    vb.cpus = 2
    # When you put your computer (while running the VM) to sleep, then resume work some time later the VM will be out
    # of sync timewise with the host for however long the host was asleep. Of course, the VM by default will
    # detect this and if the drift is great enough, it'll resync things such that the time matches, otherwise
    # the VM will just slowly adjust the timing so they'll eventually match. However, this can cause confusion when
    # times are important for late day calculations and building so we set the maximum time the VM and host can drift
    # to be 10 seconds at most which should make things work generally ideally
    vb.customize ['guestproperty', 'set', :id, '/VirtualBox/GuestAdd/VBoxService/--timesync-set-threshold', 10000 ]

    # VirtualBox sometimes has isseus with getting the DNS to work inside of itself for whatever reason.
    # While it will sometimes randomly work, we can just set VirtualBox to use a DNS proxy from the host,
    # which seems to be far more reliable in having the DNS work, rather than leaving it to VirtualBox.
    # See https://serverfault.com/a/453260 for more info.
    # vb.customize ["modifyvm", :id, "--natdnsproxy1", "on"]
    vb.customize ["modifyvm", :id, "--natdnshostresolver1", "on"]

    mount_folders(override, ["dmode=775", "fmode=664"])
  end

  config.vm.provider "parallels" do |prl, override|
    prl.memory = 2048
    prl.cpus = 2

    mount_folders(override, ["share", "nosuid"])
  end

  config.vm.provider "vmware_desktop" do |vmware, override|
    vmware.vmx["memsize"] = "2048"
    vmware.vmx["numvcpus"] = "2"

    mount_folders(override, [])
  end

  config.vm.provision :shell, :inline => " sudo timedatectl set-timezone America/New_York", run: "once"

  if ARGV.include?('ssh')
    config.ssh.username = 'root'
    config.ssh.password = 'vagrant'
    config.ssh.insert_key = 'true'
    config.ssh.timeout = 20
  end
end
