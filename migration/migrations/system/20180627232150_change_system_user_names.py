import os

def up():

    # stop all jobs that are using hwphp and hwcron
    os.system("systemctl stop submitty_autograding_worker")
    os.system("systemctl stop submitty_autograding_shipper")
    os.system("systemctl stop apache2.service")
    os.system("systemctl stop php7.0-fpm.service")
    os.system("su -c 'crontab -r' hwcron")
    os.system("su -c '/usr/local/submitty/sbin/killall.py' hwcron")

    # change the usernames
    os.system("usermod -l submitty_php hwphp")
    os.system("usermod -l submitty_cgi hwcgi")
    os.system("usermod -l submitty_daemon hwcron")
    os.system("usermod -l submitty_dbuser hsdbu")

    # change the group names
    os.system("groupmod --new-name submitty_daemonphp hwcronphp")
    os.system("groupmod --new-name submitty_course_builders course_builders")

    # cannot restart until the submitty code is installed
    print ("WARNING: You will need to manually restart the website/shipper/worker")
    print ("       systemctl start apache2.service")
    print ("       systemctl start php7.0-fpm.service")
    print ("       systemctl start submitty_autograding_shipper")
    print ("       systemctl start submitty_autograding_worker")

    os.system("mv /home/hwcron /home/submitty_daemon")
    os.system("mv /home/hwphp /home/submitty_php")
    os.system("mv /home/hwcgi /home/submitty_cgi")
    os.system("mv /home/hsdbu /home/submitty_dbuser")



    # TODO edit the variables stored by configure submitty/installation
    
    
    pass


def down():

    # stop all jobs that are using submitty_php and submitty_daemon
    os.system("systemctl stop submitty_autograding_worker")
    os.system("systemctl stop submitty_autograding_shipper")
    os.system("systemctl stop apache2.service")
    os.system("systemctl stop php7.0-fpm.service")
    os.system("su -c 'crontab -r' submitty_daemon")
    os.system("su -c '/usr/local/submitty/sbin/killall.py' submitty_daemon")

    # change the usernames
    os.system("usermod -l hwphp submitty_php")
    os.system("usermod -l hwcgi submitty_cgi")
    os.system("usermod -l hwcron submitty_daemon")
    os.system("usermod -l hsdbu submitty_dbuser")

    # change the group names
    os.system("groupmod --new-name hwcronphp submitty_daemonphp")
    os.system("groupmod --new-name course_builders submitty_course_builders")

    # cannot restart until the submitty code is installed
    print ("WARNING: You will need to manually restart the website/shipper/worker")
    print ("       systemctl start apache2.service")
    print ("       systemctl start php7.0-fpm.service")
    print ("       systemctl start submitty_autograding_shipper")
    print ("       systemctl start submitty_autograding_worker")

    os.system("mv /home/submitty_daemon /home/hwcron")
    os.system("mv /home/submitty_php /home/hwphp")
    os.system("mv /home/submitty_cgi /home/hwcgi")
    os.system("mv /home/submitty_dbuser /home/hsdbu")

    pass
