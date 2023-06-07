*************WORK IN PROGRESS************

On an M1 Mac laptop, we cannot use virtual box, so follow these instructions instead:


1. On the host computer, create a new directory named GIT_CHECKOUT to
   hold all of the Submitty git repositories.  Manually checkout these
   repositories (and make sure they are up-to-date):

   From https://github.com/Submitty

   Submitty
   AnalysisTools
   AnalysisToolsTS
   Lichen
   RainbowGrades
   SysadminTools
   Tutorial

   And also create directory:
   vendor/
   in GIT_CHECKOUT directory and checkout from:
   git clone https://github.com/nlohmann/json.git
   to this path:
   vendor/nlohmann/json/

   And optionally (currently private repos), from https://github.com/Submitty
   LichenTestData
   CrashCourseCPPSyntax

   *** FIXME: Currently we checkout all of the repositories manually,
       since the VM cannot write to the shared directory?  Unlike how
       we currently setup with vagrant, we may not be able to share
       multiple directories ***


2. Install UTM
   https://mac.getutm.app/
   Current version: 3.5.1


3. Go to: https://cdimage.ubuntu.com/releases/20.04.4/release/
   Then download and save the Ubuntu 20.04 ARM Server ISO (arm64 image)

   (UTM site reference: https://mac.getutm.app/gallery/ubuntu-20-04)

4. Launch UTM, and through the UTM GUI, press "+" to create a new VM:

   Select "Virtualize"
   Select "Linux"

   Under Boot ISO Image: browse to select the Ubuntu 20.04 ISO you just downloaded.

   memory -> 4096 mb (or more)
   CPU Cores -> 2 (or more)

   Storage, 64GB, press "Next"

   Shared Directory, Browse to specify the "GIT_CHECKOUT" directory on your host

   Review the Summary, press "Save"


5. Now press the play button to boot & install the guest machine.
   Do the interactive Ubuntu Server installation...

   * "Install Ubuntu Server"

   ... you'll wait a while here ...

   * "English"
   * (do not upgrade to Ubuntu 22.04 -- "Continue without updating")
   * "Done" on keyboard layout
   * "Done" on network connections
   * "Done" on configure proxy
   * "Done" on alternate mirror
   * Guided storage configuration / file system:

      - Select "Custom storage layout" and then "Done"
      - Select "free space" then "Add GPT Partition"
      - Keep the defaults and select "Create"

   * "Continue" on confirm destructive action
   * Fill in the profile setup (set a <USERNAME> & <PASSWORD>)

   * Press "Done" on Ubuntu Advantage

   * Select "Install OpenSSH server" and then "Done"
   * "Done" on featured server snaps

   It will initially say "Installing system" at the top of the page and then quickly switch to "Install complete!" ...

   ... now wait why the server does updates ...

   Wait a while, until it says "Reboot now"

   select "Reboot now"


6. After waiting a little while...
   NOTE: reboot only seems to stop.  It doesn't actually halt & restart.

   From the main UTM screen:

   * Turn off the virtual machine by pressing the square symbol.

     If it says "do you want to force stop this VM and lose all unsaved data?  stop or cancel"...


   * "Clear"/disconnect the removable CD/DVD drive (the ISO).

   * Click on the sliders icon in the upper right to edit the VM
     settings again.

     under the "Network" tab, add port forwarding:
       select "Emulated VLAN" for "Network Mode"
       Under the "Network" tab, select "Port Forward"
       click "New" button to on the bottom right corner &
       add the following Guest Port/Host Port pairings:
         Add the guest port number to the second text box (where it shows 1234), and host to the 4th
         guest port 22 -> host 1234 (or anything for ssh below)
         guest port 1511 -> host 1511
         guest port 8443 -> host 8443
         guest port 5432 -> host 16442

     press "save".

   * Then press the play icon to boot the machine again.


7. To ssh from your host machine to the guest vm:

   ssh -p 1234 <USERNAME>@localhost

   Run `sudo su` to connect as the root user
   
   NOTE: If you are making a new VM after having one in the past, and you receive an error
   when trying the ssh, you must reset your host key. In order to do that, go to finder, and
   get to the user folder (Usually Macintosh HD/Users), then go to the user you are on your 
   computer (Users/<your user>). Then, if the .ssh file does not appear, press CMD + Shift + . 
   (shows hidden files). Enter the .ssh file, and open known_hosts. In known hosts, delete both 
   lines starting with [localhost]:1234. Then save the file and try again. If the keys are not 
   there, try the ssh command as a supervisor (sudo ssh -p 1234 <USERNAME>@localhost), and then 
   delete the lines if you still receive the error.

8. To share directories between host & guest machines:

   On the guest machine:

   sudo apt install -y spice-vdagent spice-webdavd
   sudo apt install -y davfs2
       * Answer the question "YES" and grant access to unprivileged users *
   sudo mkdir -p /usr/local/submitty/GIT_CHECKOUT

   NOTE: The command below must be re-run each time the guest machine
   is rebooted.  It will require interactively entering the username &
   password for the host machine.  You could put those credentials on
   the command line, and in a guest machine startup script, but that
   may be a security concern.

   sudo mount -t davfs -o noexec http://127.0.0.1:9843 /usr/local/submitty/GIT_CHECKOUT

   If you get an error trying to run this from the ssh terminal, try
   running it directly in the UTM VM GUI terminal.

   If you get an error mounting the shared directory, you can try
   running the umount command below, and then repeat the mount command
   above.  If that doesn't work you can try halt, stop, and play
   (rebooting) the VM and then try again.

   sudo umount /usr/local/submitty/GIT_CHECKOUT


8b. NOTE: If step 8 fails to mount the shared directory to the host
    computer with an error "failure to reach server", try this instead
    (from https://docs.getutm.app/guest-support/linux/)

    On the guest:
   
      sudo mount -t 9p -o trans=virtio share /usr/local/submitty/GIT_CHECKOUT/ -oversion=9p2000.L


    Then to prevent ownership and permissions errors with git, type these commands on the guest:

      cd /usr/local/submitty/GIT_CHECKOUT
      git config --global --add safe.directory '*'


    Confirm that you can see files in /usr/local/submitty/GIT_CHECKOUT/Submitty and
    /usr/local/submitty/GIT_CHECKOUT/Tutorial etc.

    Confirm that you can use git to get the release version, etc.

      cd /usr/local/submitty/GIT_CHECKOUT/Tutorial
      git status

    Also check the other repositories and make sure there are not
    errors about ownership/permissions/etc.



9.  Do Submitty system setup and installation:

    On the guest machine:

    sudo bash /usr/local/submitty/GIT_CHECKOUT/Submitty/.setup/install_system.sh --utm

    Hopefully it completes without error or network problems... if you
    have errors, you can try to re-run the above command.  However, if
    it crashes in the middle of creating the sample course data, you
    may need to do:

    sudo bash /usr/local/submitty/GIT_CHECKOUT/Submitty/.setup/bin/recreate_sample_courses.sh




10. When finished, access the Submitty website from a browser on your host machine:

    http://localhost:1511/
