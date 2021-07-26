*************WORK IN PROGRESS************

On an M1 Mac laptop, we cannot use virtual box, so follow these instructions instead:


1. Install UTM
   https://mac.getutm.app/


2. Download and save the Ubuntu 20.04 ARM Server ISO
   https://mac.getutm.app/gallery/ubuntu-20-04


3. Through the UTM GUI, create a new VM:

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


4. On the host computer, create a new directory named GIT_CHECKOUT to
   hold all of the Submitty git repositories.  Manually checkout these
   repositories:

   From https://github.com/Submitty

   Submitty
   AnalysisTools
   CrashCourseCPPSyntax
   Lichen
   RainbowGrades
   SysadminTools
   Tutorial

   And also create directory:
   vendor/nlohmann/json/

   from:
   git clone https://github.com/nlohmann/json.git

   Optionally (currently a private repo), from https://github.com/Submitty
   LichenTestData

   *** FIXME: Currently we checkout all of the repositories manually,
       since the VM cannot write to the shared directory?  Unlike how
       we currently setup with vagrant, we may not be able to share
       multiple directories ***


5. From the main screen, with this new VM selected:

   -> set the CD/DVD drive to point to the ISO you downloaded to your
      host machine earlier

   -> set the shared directory to point to the GIT_CHECKOUT directory
      that holds your Submitty git repositories on your host machine


6. Now boot & install the guest machine.  Do the interactive Ubuntu
   Server installation...
   
   You'll set a <USERNAME> & <PASSWORD>
 

   *** TODO: could be more explicit about other things in the
       interactive setup? ***


7. Turn off the virtual machine, and from the main UTM screen,
   disconnect the removable CD/DVD drive.  Then reboot.


8. To ssh from your host machine to the guest vm:

   ssh -p 1234 <USERNAME>@localhost


9. To share directories between host & guest machines:

   On the guest machine:

   sudo apt install spice-vdagent spice-webdavd
   sudo apt install davfs2

   sudo mkdir -p /usr/local/submitty


   NOTE: The command below must be re-run each time the guest machine
   is rebooted.  It will require interactively entering the username &
   password for the host machine.  You could put those credentials on
   the command line, and in a guest machine startup script, but that
   may be a security concern.

   sudo mount -t davfs -o noexec http://127.0.0.1:9843 /usr/local/submitty/GIT_CHECKOUT


10. Do Submitty system setup and installation:

    On the guest machine:

    sudo bash /usr/local/submitty/GIT_CHECKOUT/Submitty/.setup/install_system.sh --vagrant


11. When finished, access the Submitty website from a browser on your host machine:

    http://localhost:1511/



