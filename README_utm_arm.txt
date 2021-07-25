*************WORK IN PROGRESS************

On an M1 Mac laptop, we cannot use virtual box, so follow these instructions instead:

1. Install UTM
   https://mac.getutm.app/

2. Download and save the Ubuntu 20.04 ARM Server ISO
   https://mac.getutm.app/gallery/ubuntu-20-04

3. Create a new VM

   under the system tab, specify
   architecture arm64
   2048 mb of RAM
   under advanced
   2 core

   make 2 drives
   the first one is a removable usb
   the second one has at least 10 gb

   link the removable usb to the iso you downloaded earlier

   under the sharing tab

   add port forwarding
   guest 22
   host 1234 (or anything)

   specify that you want to share the directory on your host with your Submitty git repositories

4. boot & install the machine

   you'll set a <USERNAME> & <PASSWORD>

5. disconnect the removable usb drive & reboot

6. To ssh from your host to the vm:

   ssh -p 1234 <USERNAME>@localhost


7. Share directories back to your host machine

   sudo apt install spice-vdagent spice-webdavd
   sudo apt install davfs2

   sudo mkdir -p /usr/local/submitty/GIT_CHECKOUT

   sudo emacs /etc/rc.local

   put this in that file:
   #!/bin/bash
   sudo mount -t davfs -o noexec http://127.0.0.1:9843 /usr/local/submitty/GIT_CHECKOUT

   ** Whoops, this is an interactive script requiring your host username & password ** FIXME

   sudo chmod +x /etc/rc.local

