*************WORK IN PROGRESS************

On an M1 Mac laptop, we cannot use virtual box, so follow these instructions instead:


1. On the host computer, create a new directory named GIT_CHECKOUT to
   hold all of the Submitty git repositories.  Manually checkout these
   repositories (and make sure they are up-to-date):

   From https://github.com/Submitty

   Submitty
   AnalysisTools
   CrashCourseCPPSyntax
   Lichen
   RainbowGrades
   SysadminTools
   Tutorial

   And also create directory:
   vendor/
   and checkout from:
   git clone https://github.com/nlohmann/json.git
   to this path:
   vendor/nlohmann/json/

   Optionally (currently a private repo), from https://github.com/Submitty
   LichenTestData

   *** FIXME: Currently we checkout all of the repositories manually,
       since the VM cannot write to the shared directory?  Unlike how
       we currently setup with vagrant, we may not be able to share
       multiple directories ***


2. Install UTM
   https://mac.getutm.app/
   Current version: 3.5.1


3. Download and save the Ubuntu 20.04 ARM Server ISO
   https://mac.getutm.app/gallery/ubuntu-20-04
   https://cdimage.ubuntu.com/releases/20.04.4/release/


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
   * "Done" on default for storage configuration / storage layout
   * "Done" on default for storage configuration / file system

      - set the "device" "mounted at /" to be most of your space (e.g. 60GB)
        it probably defaulted to 30BG (leaving 30GB of free)

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
       guest port 22 -> host 1234 (or anything for ssh below)
       guest port 1511 -> host 1511
       guest port 8443 -> host 8443
       guest port 5432 -> host 16442

     press "save".

   * Then press the play icon to boot the machine again.


7. To ssh from your host machine to the guest vm:

   ssh -p 1234 <USERNAME>@localhost


8. To share directories between host & guest machines:

   On the guest machine:

   sudo apt install -y spice-vdagent spice-webdavd
   sudo apt install -y davfs2
       * Answer the question "YES" and grant access to unpriviledged users *
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


9. TEMPORARY HACK STEP

   open .setup/pip/system_requirements.txt
   comment out the opencv and onnx version installations (compilation from scratch fails)

    #opencv-python==3.4.10.37
    #onnxruntime==1.8.1
    #onnx==1.9.0


10. Do Submitty system setup and installation:

    On the guest machine:

    sudo bash /usr/local/submitty/GIT_CHECKOUT/Submitty/.setup/install_system.sh --vagrant

    Hopefully it completes without error or network problems... if you
    have errors, you can try to re-run the above command.  However, if
    it crashes in the middle of creating the sample course data, you
    may need to do:

    sudo bash /usr/local/submitty/GIT_CHECKOUT/Submitty/.setup/bin/recreate_sample_courses.sh


11. After installation, to fix opencv & onnx:

    sudo pip install opencv-python
    sudo pip install onnxruntime
    sudo apt install libgl1-mesa-glx


12. When finished, access the Submitty website from a browser on your host machine:

    http://localhost:1511/
