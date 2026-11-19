packer {
  required_plugins {
    virtualbox = {
      version = ">= 1.1.0"
      source  = "github.com/hashicorp/virtualbox"
    }
    vagrant = {
      version = ">= 1.1.0"
      source  = "github.com/hashicorp/vagrant"
    }
  }
}

# amd64 or arm64. VirtualBox can't emulate another CPU, so this must match the host.
variable "arch" {
  type    = string
  default = "amd64"
  validation {
    condition     = contains(["amd64", "arm64"], var.arch)
    error_message = "The arch variable must be amd64 or arm64."
  }
}

# Leave empty to download the official live server ISO for var.arch.
variable "iso_url" {
  type    = string
  default = ""
}

variable "iso_checksum" {
  type    = string
  default = ""
}

variable "output_box" {
  type    = string
  default = ""
}

variable "cpus" {
  type    = number
  default = 2
}

variable "memory" {
  type    = number
  default = 4096
}

# In MB. The disk is dynamically allocated, so this is only the maximum size.
variable "disk_size" {
  type    = number
  default = 65536
}

# How long to wait after power-on before typing the boot command. Keystrokes sent before GRUB is
# ready are lost and the installer starts without autoinstall, so slow hosts (CI) need longer.
# Keep it under GRUB's 30 second menu timeout.
variable "boot_wait" {
  type    = string
  default = "15s"
}

# How long the OS install may take before Packer gives up. Hosts without hardware virtualization
# (VirtualBox "turtle mode", e.g. GitHub's macOS Intel runners) are many times slower.
variable "ssh_timeout" {
  type    = string
  default = "60m"
}

variable "headless" {
  type    = bool
  default = true
}

locals {
  platform = {
    amd64 = {
      iso_url       = "https://releases.ubuntu.com/22.04/ubuntu-22.04.5-live-server-amd64.iso"
      iso_checksum  = "file:https://releases.ubuntu.com/22.04/SHA256SUMS"
      guest_os_type = "Ubuntu_64"
      chipset       = "piix3"
      firmware      = "bios"
      disk_iface    = "sata"
      iso_iface     = "sata"
      gfx           = "vmsvga"
      nic           = "82540EM"
      usb           = "none"
      keyboard      = "ps2"
      mouse         = "ps2"
    }
    # VirtualBox 7.1+ on an ARM host (e.g. Apple Silicon); ARM VMs only boot via EFI and lack legacy PC devices.
    arm64 = {
      iso_url       = "https://cdimage.ubuntu.com/releases/22.04/release/ubuntu-22.04.5-live-server-arm64.iso"
      iso_checksum  = "file:https://cdimage.ubuntu.com/releases/22.04/release/SHA256SUMS"
      guest_os_type = "Ubuntu_arm64"
      chipset       = "armv8virtual"
      firmware      = "efi"
      disk_iface    = "virtio"
      iso_iface     = "virtio"
      gfx           = "qemuramfb"
      nic           = "virtio"
      usb           = "xhci"
      keyboard      = "usb"
      mouse         = "usbtablet"
    }
  }
  p = local.platform[var.arch]
}

source "virtualbox-iso" "ubuntu" {
  vm_name       = "ubuntu-22.04-${var.arch}-base"
  guest_os_type = local.p.guest_os_type
  iso_url       = var.iso_url != "" ? var.iso_url : local.p.iso_url
  iso_checksum  = var.iso_checksum != "" ? var.iso_checksum : local.p.iso_checksum

  cpus      = var.cpus
  memory    = var.memory
  disk_size = var.disk_size
  headless  = var.headless

  chipset        = local.p.chipset
  firmware       = local.p.firmware
  gfx_controller = local.p.gfx
  gfx_vram_size  = 16
  nic_type       = local.p.nic
  usb            = local.p.usb != "none"
  usb_controller = local.p.usb
  keyboard       = local.p.keyboard
  mouse          = local.p.mouse

  hard_drive_interface     = local.p.disk_iface
  iso_interface            = local.p.iso_iface
  hard_drive_discard       = true
  hard_drive_nonrotational = true

  # Serves http/user-data and http/meta-data to the installer.
  http_directory = "${path.root}/http"

  # Edit the default GRUB entry to add autoinstall to the kernel line, then boot it with F10.
  # Editing the entry (rather than typing linux/initrd/boot at the GRUB console) lets GRUB load
  # the kernel and initrd itself, so nothing typed can be lost while a slow VM is busy loading.
  boot_wait = var.boot_wait
  boot_command = [
    "e<wait>",
    "<down><down><down><end>",
    " autoinstall ds=\"nocloud-net;s=http://{{ .HTTPIP }}:{{ .HTTPPort }}/\"",
    "<f10>",
  ]

  ssh_username = "vagrant"
  ssh_password = "vagrant"
  ssh_timeout  = var.ssh_timeout
  # The installer's own SSH server is up during install and rejects us; keep retrying until the real system boots.
  ssh_handshake_attempts = 500

  shutdown_command = "echo 'vagrant' | sudo -S shutdown -P now"

  guest_additions_mode = "upload"
  guest_additions_path = "VBoxGuestAdditions.iso"

  vboxmanage = [
    ["modifyvm", "{{ .Name }}", "--rtcuseutc", "on"],
    ["modifyvm", "{{ .Name }}", "--audio-enabled", "off"],
  ]
}

build {
  sources = ["source.virtualbox-iso.ubuntu"]

  provisioner "shell" {
    execute_command   = "echo 'vagrant' | {{ .Vars }} sudo -S -E bash -eu '{{ .Path }}'"
    script            = "${path.root}/scripts/update.sh"
    expect_disconnect = true
  }

  provisioner "shell" {
    execute_command = "echo 'vagrant' | {{ .Vars }} sudo -S -E bash -eu '{{ .Path }}'"
    pause_before    = "30s"
    scripts = [
      "${path.root}/scripts/vagrant.sh",
      "${path.root}/scripts/guest-additions.sh",
      "${path.root}/scripts/cleanup.sh",
    ]
  }

  post-processor "vagrant" {
    output            = var.output_box != "" ? var.output_box : "output/ubuntu-22.04-${var.arch}-virtualbox.box"
    compression_level = 6
  }
}
