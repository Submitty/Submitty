*************WORK IN PROGRESS************

On an M1 Mac laptop, we cannot use virtual box, so follow these instructions instead:

1. Install UTM
   https://mac.getutm.app/

2. Download and save the Ubuntu 20.04 ARM Server ISO
   https://mac.getutm.app/gallery/ubuntu-20-04

3. Through the UTM GUI, create a new VM

   under the "System" tab, specify:
   architecture -> ARM64 (aarch64)
   system -> QEMU 6.0 ARM Virtual Machine
   memory -> 2048 mb
   click on "show advanced settings"
   CPU Cores -> 2

   under the "Drives" tab, make 2 drives:
   the first one is a "removable drive" CD/DVD (ISO) image, USB interface
   the second one is a "disk image" with "virtIO" that is at least 10 GB (I did 20)

   under the "Network" tab, add port forwarding:
   guest port 22 -> host 1234 (or anything for ssh below)
   guest port 1511 -> host 1511
   guest port 8443 -> host 8443
   guest port 5432 -> host 16442

   under the "Sharing" tab,
   -> "enable clipboard sharing"
   -> "enable directory sharing"

   

4. from the main screen, with this new VM selected:

   -> set the CD/DVD drrive to point to the iso you downloaded earlier

   -> set the shared directory to point to the GIT_CHECKOUT directory
      that holds your Submitty git repositories on your host machine

      *** FIXME, might want to do something different with the sharing? ***

5. boot & install the machine

   do the interactive setup...
   
   you'll set a <USERNAME> & <PASSWORD>
 
      *** could be more explicit about other things in the interactive setup? ***

6. turn off the virtual machine, and from the main screen,

   -> disconnect the removable usb drive & reboot

7. To ssh from your host to the vm:

   ssh -p 1234 <USERNAME>@localhost

8. Share directories back to your host machine

   sudo apt install spice-vdagent spice-webdavd
   sudo apt install davfs2

   sudo mkdir -p /usr/local/submitty/GIT_CHECKOUT

      *** FIXME, might want to do something different with the
          sharing, the installation scripts cannot write new repos to
          this directory(??), or maybe some setting is off preventing
          writing? ***


   setup to do this automatically on boot -- doesn't work

   sudo emacs /etc/rc.local

   put this in that file:
   #!/bin/bash
   sudo mount -t davfs -o noexec http://127.0.0.1:9843 /usr/local/submitty/GIT_CHECKOUT

      ** FIXME, whoops, this is an interactive script requiring your host
         username & password **

   sudo chmod +x /etc/rc.local

