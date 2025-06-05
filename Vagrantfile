# -*- mode: ruby -*-
# vi: set ft=ruby :

# Usage:
#   vagrant up
#       or
#   NO_SUBMISSIONS=1 vagrant up
#       or
#   EXTRA=rpi vagrant up
#
#
# If you want to override the default image used for the virtual machines, you can set the
# environment variable VAGRANT_BOX. See https://vagrantup.com/boxes/search for a list of
# distributed boxes. For example:
#
# VAGRANT_BOX=ubuntu/focal64 vagrant up
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

require 'json'

ON_CI = !ENV.fetch('CI', '').empty?

def gen_script(machine_name, worker: false, base: false)
  no_submissions = !ENV.fetch('NO_SUBMISSIONS', '').empty?
  reinstall = ENV.has_key?('VAGRANT_BOX') || base
  extra = ENV.fetch('EXTRA', '')  
  setup_cmd = 'bash ${GIT_PATH}/.setup/'
  if reinstall || ON_CI
    if worker
      setup_cmd += 'install_worker.sh'
    else
      setup_cmd += 'vagrant/setup_vagrant.sh'
      if no_submissions
        setup_cmd += ' --no_submissions'
      end
      if ON_CI
        setup_cmd += ' --ci'
      end
    end
  else
    setup_cmd += 'install_success_from_cloud.sh'
  end
  unless extra.empty?
    setup_cmd += " #{extra}"
  end
  setup_cmd += " 2>&1 | tee ${GIT_PATH}/.vagrant/logs/#{machine_name}.log"

  script = <<SCRIPT
    GIT_PATH=/usr/local/submitty/GIT_CHECKOUT/Submitty
    DISTRO=$(lsb_release -si | tr '[:upper:]' '[:lower:]')
    VERSION=$(lsb_release -sr | tr '[:upper:]' '[:lower:]')
    mkdir -p ${GIT_PATH}/.vagrant/logs
    #{setup_cmd}
SCRIPT

  return script
end

base_boxes = Hash[]

# Should all be base Ubuntu boxes that use the same version
base_boxes.default         = "SubmittyBot/ubuntu22-dev"
base_boxes[:base]          = "bento/ubuntu-22.04"
base_boxes[:arm_bento]     = "bento/ubuntu-22.04-arm64"
base_boxes[:libvirt]       = "generic/ubuntu2204"
base_boxes[:arm_mac_qemu]  = "perk/ubuntu-2204-arm64"


def mount_folders(config, mount_options, type = nil)
 # ideally we would use submitty_daemon or something as the owner/group, but since that user doesn't exist
  # till post-provision (and this is mounted before provisioning), we want the group to be 'vagrant'
  # which is guaranteed to exist and that during install_system.sh we add submitty_daemon/submitty_php/etc to the
  # vagrant group so that they can write to this shared folder, primarily just for the log files
  owner = 'root'
  group = 'vagrant'
  config.vm.synced_folder '.', '/usr/local/submitty/GIT_CHECKOUT/Submitty', create: true, owner: owner, group: group, mount_options: mount_options, smb_host: '10.0.2.2', smb_username: `whoami`.chomp, type: type

  optional_repos = %w(AnalysisTools AnalysisToolsTS Lichen RainbowGrades Tutorial CrashCourseCPPSyntax IntroQuantumComputing LichenTestData DockerImages DockerImagesRPI)
  optional_repos.each {|repo|
    repo_path = File.expand_path("../" + repo)
    if File.directory?(repo_path)
      config.vm.synced_folder repo_path, "/usr/local/submitty/GIT_CHECKOUT/" + repo, owner: owner, group: group, mount_options: mount_options, smb_host: '10.0.2.2', smb_username: `whoami`.chomp, type:type
    end
  }
end

def get_workers()
  worker_file = File.join(__dir__, '.vagrant', 'workers.json')
  if File.file?(worker_file)
    return JSON.parse(File.read(worker_file), symbolize_names: true)
  else
    return Hash[]
  end
end

Vagrant.configure(2) do |config|
  if Vagrant.has_plugin?('vagrant-env')
    config.env.enable
  end

  # Warn the user if they attempt to re-provision
  if ARGV[0] == 'provision' and not Dir.glob(".vagrant/machines/*/*/action_provision").empty?
    print "\e[31mWarning: A virtual machine has already been provisioned. Re-provisioning may destroy this existing virtual machine state.\nAre you sure you would like to proceed?\e[0m [y/N] "
    if STDIN.gets.chomp.downcase != 'y'
      exit
    end
  end

  if ON_CI
    config.ssh.insert_key = false
  else 
    config.ssh.insert_key = true
  end

  mount_options = []

  config.vm.box = ENV.fetch('VAGRANT_BOX', base_boxes.default)
  
  arch = `uname -m`.chomp
  arm = arch == 'arm64' || arch == 'aarch64'
  apple_silicon = Vagrant::Util::Platform.darwin? && (arm || (`sysctl -n machdep.cpu.brand_string`.chomp.start_with? 'Apple M'))
  use_prebuilt_version = !ENV.fetch('PREBUILT_VERSION', '').empty?
  custom_box = ENV.has_key?('VAGRANT_BOX') 
  base_box = ENV.has_key?('BASE_BOX') || ENV.has_key?('FROM_SCRATCH') || apple_silicon || arm
  # The time in seconds that Vagrant will wait for the machine to boot and be accessible.
  config.vm.boot_timeout = 600

  # Specify the various machines that we might develop on. After defining a name, we
  # can specify if the vm is our "primary" one (if we don't specify a VM, it'll use
  # that one) as well as making sure all non-primary ones have "autostart: false" set
  # so that when we do "vagrant up", it doesn't spin up those machines.

  get_workers.map do |worker_name, data|
    config.vm.define worker_name do |ubuntu|
      ubuntu.vm.network 'private_network', ip: data[:ip_addr]
      ubuntu.vm.network 'forwarded_port', guest: 22, host: data[:ssh_port], id: 'ssh'
      ubuntu.vm.provision 'shell', inline: gen_script(worker_name, worker: true, base: base_box)
    end
  end

  vm_name = 'ubuntu-22.04'
  config.vm.define vm_name, primary: true do |ubuntu|
    ubuntu.vm.network 'forwarded_port', guest: 1511, host: ENV.fetch('VM_PORT_SITE', 1511)
    ubuntu.vm.network 'forwarded_port', guest: 8443, host: ENV.fetch('VM_PORT_WS',   8443)
    ubuntu.vm.network 'forwarded_port', guest: 5432, host: ENV.fetch('VM_PORT_DB',  16442)
    ubuntu.vm.network 'forwarded_port', guest: 7000, host: ENV.fetch('VM_PORT_SAML', 7001)
    ubuntu.vm.network 'forwarded_port', guest:   22, host: ENV.fetch('VM_PORT_SSH',  2222), id: 'ssh'
    ubuntu.vm.provision 'shell', inline: gen_script(vm_name, base: base_box)
  end

  config.vm.provider 'virtualbox' do |vb, override|
    unless custom_box
      if base_box || ON_CI
        override.vm.box = base_boxes[:base]
      else
        config.ssh.username = 'root'
        if use_prebuilt_version
          override.vm.box_version = ENV.fetch('PREBUILT_VERSION')
        end
      end
    end

    # We limit resources when running on CI to avoid resource exhaustion and it isn't used for grading stuff or
    # other things we do in dev.
    vb.memory = ON_CI ? 1024 : 2048
    vb.cpus = ON_CI ? 1 : 2

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

    # Sometimes Vagrant will error on trying to SSH into the machine when it starts, due to a bug in how
    # Ubuntu 20.04 and later setup the virtual serial port, where the machine takes minutes to start, plus
    # occasionally restarting. Modifying the behavior of the uart fields, as well as disabling features like USB and
    # audio (which we don't need) seems to greatly reduce boot times and make vagrant work consistently. See
    # https://github.com/hashicorp/vagrant/issues/11777 for more info.
    vb.customize ["modifyvm", :id, "--uart1", "0x3F8", "4"]
    vb.customize ["modifyvm", :id, "--uartmode1", "file", File::NULL]
    vb.customize ["modifyvm", :id, "--audio", "none"]
    vb.customize ["modifyvm", :id, "--usb", "off"]
    vb.customize ["modifyvm", :id, "--uart1", "off"]
    vb.customize ["modifyvm", :id, "--uart2", "off"]
    vb.customize ["modifyvm", :id, "--uart3", "off"]
    vb.customize ["modifyvm", :id, "--uart4", "off"]

    mount_folders(override, ["dmode=775", "fmode=664"])

    if ARGV.include?('ssh')
      override.ssh.timeout = 20
    end
  end

  config.vm.provider "parallels" do |prl, override|
    unless custom_box
      if (arm || apple_silicon)
        override.vm.box = base_boxes[:arm_bento]
      end
    end

    prl.memory = 2048
    prl.cpus = 2

    mount_folders(override, ["share", "nosuid"])
  end

  config.vm.provider "vmware_desktop" do |vmware, override|
    unless custom_box
      if (arm || apple_silicon)
        override.vm.box = base_boxes[:arm_bento]
      end
    end
    vmware.vmx["memsize"] = "2048"
    vmware.vmx["numvcpus"] = "2"

    mount_folders(override, [])
  end
  
  config.vm.provider "libvirt" do |libvirt, override|
    unless custom_box
      override.vm.box = base_boxes[:libvirt]
    end

    libvirt.qemu_use_session = true

    libvirt.memory = 2048
    libvirt.cpus = 2

    libvirt.forward_ssh_port = true

    mount_folders(override, [], "rsync")
  end

  config.vm.provider "qemu" do |qe, override|
    unless custom_box
      if apple_silicon
        override.vm.box = base_boxes[:arm_mac_qemu]
      end
    end

    qe.memory = "2G"
    qe.smp = 2

    qe.ssh_port = ENV.fetch('VM_PORT_SSH', 2222)

    mount_folders(override, [])
  end

  config.vm.provision :shell, :inline => " sudo timedatectl set-timezone America/New_York", run: "once"


  # to use the command below, first install the vagrant plugin:
  #
  #   vagrant plugin install vagrant-timezone
  #
  if Vagrant.has_plugin?("vagrant-timezone")
    config.timezone.value = "America/New_York"
  end

  if ARGV.include?('ssh')
    config.ssh.username = 'root'
    config.ssh.password = 'vagrant'
  end 
end
