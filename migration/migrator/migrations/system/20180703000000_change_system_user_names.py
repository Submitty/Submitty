import os
import json
import shutil
import grp
from pathlib import Path

def change_key(my_json, old_key, new_key):
    if old_key in my_json:
        # store the value
        val = my_json[old_key]
        # delete the old key/value pair
        del my_json[old_key]
        # error checking
        if new_key in my_json:
            print ("ERROR: new_key "+new_key+" is already in the dictionary")
        # re-add the value with the new key
        my_json[new_key] = val
    else:
        print ("ERROR: old_key "+old_key+" is not in the dictionary")


def change_value(my_json, old_key, new_value):
    if old_key in my_json:
        my_json[old_key] = new_value
    else:
        print ("ERROR: old_key "+old_key+" is not in the dictionary")

        
def up(config):

    submitty_filename = str(Path(config.submitty['submitty_install_dir'], 'config', 'submitty.json'))
    submitty_filename_tmp = str(Path(config.submitty['submitty_install_dir'], 'config', 'submitty_tmp.json'))
    submitty_users_filename = str(Path(config.submitty['submitty_install_dir'], 'config', 'submitty_users.json'))
    submitty_users_filename_tmp = str(Path(config.submitty['submitty_install_dir'], 'config', 'submitty_users_tmp.json'))
    killall_path = Path(config.submitty['submitty_install_dir'], 'sbin', 'killall.py')
    INSTALL_SUBMITTY_filename = str(Path(config.submitty['submitty_install_dir'], '.setup', 'INSTALL_SUBMITTY.sh'))
    
    print ("my path",killall_path)

    # stop the old scheduler daemon, if it is still in use -- was deprecated in early Spring 2018
    os.system("systemctl stop submitty_grading_scheduler")
    try:
        os.remove("/etc/systemd/system/submitty_grading_scheduler.service")
    except OSError:
        pass

    # stop all jobs that are using hwphp and hwcron
    os.system("systemctl stop submitty_autograding_worker")
    os.system("systemctl stop submitty_autograding_shipper")
    os.system("systemctl stop apache2.service")
    os.system("systemctl stop php7.0-fpm.service")
    os.system("su -c 'crontab -r' hwcron")
    os.system("su -c '"+str(killall_path)+"' hwcron")

    # change the usernames
    os.system("usermod -l submitty_php hwphp")
    os.system("usermod -l submitty_cgi hwcgi")
    os.system("usermod -l submitty_daemon hwcron")

    # change the group names
    os.system("groupmod --new-name submitty_daemon hwcron")
    os.system("groupmod --new-name submitty_php hwphp")
    os.system("groupmod --new-name submitty_cgi hwcgi")
    os.system("groupmod --new-name submitty_daemonphp hwcronphp")
    os.system("groupmod --new-name submitty_course_builders course_builders")

    # cannot restart until the submitty code is installed
    print ("WARNING: You will need to manually restart the website/shipper/worker")
    print ("       systemctl start apache2.service")
    print ("       systemctl start php7.0-fpm.service")
    print ("       systemctl start submitty_autograding_shipper")
    print ("       systemctl start submitty_autograding_worker")

    if os.path.exists("/home/hwcron"):
        shutil.move("/home/hwcron","/home/submitty_daemon")
    if os.path.exists("/home/hwphp"):
        shutil.move("/home/hwphp","/home/submitty_php")
    if os.path.exists("/home/hwcgi"):
        shutil.move("/home/hwcgi","/home/submitty_cgi")

    # edit the variables stored by configure submitty/installation
    with open (submitty_filename,"r") as open_file:
        my_json = json.load(open_file)
    change_value(my_json,"submitty_repository","/usr/local/submitty/GIT_CHECKOUT/Submitty")
    with open (submitty_filename_tmp,"w") as open_file:
        json.dump(my_json,open_file,indent=4)
    # write to another file & then remove the write permissions
    shutil.move(submitty_filename_tmp,submitty_filename)
    os.chmod(submitty_filename, 0o440)
    os.chown(submitty_filename, os.getuid(), grp.getgrnam('submitty_daemonphp').gr_gid)

    with open (submitty_users_filename,"r") as open_file:
        my_json = json.load(open_file)
    change_key(my_json,"hwcron_uid","daemon_uid")
    change_key(my_json,"hwcron_gid","daemon_gid")
    change_key(my_json,"hwcron_user","daemon_user")
    change_value(my_json,"daemon_user","submitty_daemon")
    change_value(my_json,"course_builders_group","submitty_course_builders")
    change_key(my_json,"hwphp_uid","php_uid")
    change_key(my_json,"hwphp_gid","php_gid")
    change_key(my_json,"hwphp_user","php_user")
    change_value(my_json,"php_user","submitty_php")
    change_key(my_json,"hwcgi_user","cgi_user")
    change_value(my_json,"cgi_user","submitty_cgi")
    change_key(my_json,"hwcronphp_group","daemonphp_group")
    change_value(my_json,"daemonphp_group","submitty_daemonphp")
    with open (submitty_users_filename_tmp,"w") as open_file:
        json.dump(my_json,open_file,indent=4)
    # write to another file & then remove the write permissions
    shutil.move(submitty_users_filename_tmp,submitty_users_filename)
    os.chmod(submitty_users_filename, 0o440)
    os.chown(submitty_users_filename, os.getuid(), grp.getgrnam('submitty_daemonphp').gr_gid)

    os.chmod(INSTALL_SUBMITTY_filename, 0o700)
    os.system("sed -i -e \"s|'course_builders'|'submitty_course_builders'|g\" "+INSTALL_SUBMITTY_filename)
    os.system("sed -i -e \"s|HWCRON_USER='hwcron'|DAEMON_USER='submitty_daemon'|g\" "+INSTALL_SUBMITTY_filename)
    os.system("sed -i -e \"s|HWCRON_UID|DAEMON_UID|g\" "+INSTALL_SUBMITTY_filename)
    os.system("sed -i -e \"s|HWCRON_GID|DAEMON_GID|g\" "+INSTALL_SUBMITTY_filename)
    os.system("sed -i -e \"s|HWPHP_USER='hwphp'|PHP_USER='submitty_php'|g\" "+INSTALL_SUBMITTY_filename)
    os.system("sed -i -e \"s|HWPHP_UID|PHP_UID|g\" "+INSTALL_SUBMITTY_filename)
    os.system("sed -i -e \"s|HWPHP_GID|PHP_GID|g\" "+INSTALL_SUBMITTY_filename)
    os.system("sed -i -e \"s|HWCGI_USER='hwcgi'|CGI_USER='submitty_cgi'|g\" "+INSTALL_SUBMITTY_filename)
    os.system("sed -i -e \"s|HWCRONPHP_GROUP='hwcronphp'|DAEMONPHP_GROUP='submitty_daemonphp'|g\" "+INSTALL_SUBMITTY_filename)
    os.chmod(INSTALL_SUBMITTY_filename, 0o500)
    
    # repair & restart apache & phpfpm
    APACHE_FILENAME="/etc/apache2/sites-enabled/submitty.conf"
    os.system("sed -i -e \"s|hwcgi|submitty_cgi|g\" "+APACHE_FILENAME)
    APACHE_FILENAME2="/etc/apache2/sites-enabled/submitty_http.conf"
    os.system("sed -i -e \"s|hwcgi|submitty_cgi|g\" "+APACHE_FILENAME2)
    APACHE_FILENAME3="/etc/apache2/sites-enabled/vcs.conf"
    os.system("sed -i -e \"s|hwcgi|submitty_cgi|g\" "+APACHE_FILENAME3)
    PHPFPM_FILENAME="/etc/php/7.0/fpm/pool.d/submitty.conf"
    os.system("sed -i -e \"s|hwphp|submitty_php|g\" "+PHPFPM_FILENAME)
    os.system("systemctl start apache2.service")
    os.system("systemctl start php7.0-fpm.service")

    print ("finished migration changing system user names")
    
    pass


def down(config):

    submitty_users_filename = str(Path(config.submitty['submitty_install_dir'], 'config', 'submitty_users.json'))
    submitty_users_filename_tmp = str(Path(config.submitty['submitty_install_dir'], 'config', 'submitty_users_tmp.json'))
    killall_path = Path(config.submitty['submitty_install_dir'], 'sbin', 'killall.py')
    INSTALL_SUBMITTY_filename = str(Path(config.submitty['submitty_install_dir'], '.setup', 'INSTALL_SUBMITTY.sh'))

    # stop all jobs that are using submitty_php and submitty_daemon
    os.system("systemctl stop submitty_autograding_worker")
    os.system("systemctl stop submitty_autograding_shipper")
    os.system("systemctl stop apache2.service")
    os.system("systemctl stop php7.0-fpm.service")
    os.system("su -c 'crontab -r' submitty_daemon")
    os.system("su -c '"+str(killall_path)+"' submitty_daemon")
        
    # change the usernames
    os.system("usermod -l hwphp submitty_php")
    os.system("usermod -l hwcgi submitty_cgi")
    os.system("usermod -l hwcron submitty_daemon")

    # change the group names
    os.system("groupmod --new-name hwcron submitty_daemon")
    os.system("groupmod --new-name hwphp submitty_php")
    os.system("groupmod --new-name hwcgi submitty_cgi")
    os.system("groupmod --new-name hwcronphp submitty_daemonphp")
    os.system("groupmod --new-name course_builders submitty_course_builders")

    # cannot restart until the submitty code is installed
    print ("WARNING: You will need to manually restart the website/shipper/worker")
    print ("       systemctl start apache2.service")
    print ("       systemctl start php7.0-fpm.service")
    print ("       systemctl start submitty_autograding_shipper")
    print ("       systemctl start submitty_autograding_worker")

    if os.path.exists("/home/submitty_daemon"):
        shutil.move("/home/submitty_daemon","/home/hwcron")
    if os.path.exists("/home/submitty_php"):
        shutil.move("/home/submitty_php","/home/hwphp")
    if os.path.exists("/home/submitty_cgi"):
        shutil.move("/home/submitty_cgi","/home/hwcgi")

    # edit the variables stored by configure submitty/installation
    with open (submitty_users_filename,"r") as open_file:
        my_json = json.load(open_file)

    change_key(my_json,"daemon_uid","hwcron_uid")
    change_key(my_json,"daemon_gid","hwcron_gid")
    change_key(my_json,"daemon_user","hwcron_user")
    change_value(my_json,"hwcron_user","hwcron")
    change_value(my_json,"course_builders_group","course_builders")
    change_key(my_json,"php_uid","hwphp_uid")
    change_key(my_json,"php_gid","hwphp_gid")
    change_key(my_json,"php_user","hwphp_user")
    change_value(my_json,"hwphp_user","hwphp")
    change_key(my_json,"cgi_user","hwcgi_user")
    change_value(my_json,"hwcgi_user","hwcgi")
    change_key(my_json,"daemonphp_group","hwcronphp_group")
    change_value(my_json,"hwcronphp_group","hwcronphp")
    # write to another file & then remove the write permissions
    with open (submitty_users_filename_tmp,"w") as open_file:
        json.dump(my_json,open_file,indent=4)
    shutil.move(submitty_users_filename_tmp,submitty_users_filename)
    os.chmod(submitty_users_filename, 0o440)
    os.chown(submitty_users_filename, os.getuid(), grp.getgrnam('hwcronphp').gr_gid)

    os.chmod(INSTALL_SUBMITTY_filename, 0o700)
    os.system("sed -i -e \"s|'submitty_course_builders'|'course_builders'|g\" "+INSTALL_SUBMITTY_filename)
    os.system("sed -i -e \"s|DAEMON_USER='submitty_daemon'|HWCRON_USER='hwcron'|g\" "+INSTALL_SUBMITTY_filename)
    os.system("sed -i -e \"s|DAEMON_UID|HWCRON_UID|g\" "+INSTALL_SUBMITTY_filename)
    os.system("sed -i -e \"s|DAEMON_GID|HWCRON_GID|g\" "+INSTALL_SUBMITTY_filename)
    os.system("sed -i -e \"s|PHP_USER='submitty_php'|HWPHP_USER='hwphp'|g\" "+INSTALL_SUBMITTY_filename)
    os.system("sed -i -e \"s|PHP_UID|HWPHP_UID|g\" "+INSTALL_SUBMITTY_filename)
    os.system("sed -i -e \"s|PHP_GID|HWPHP_GID|g\" "+INSTALL_SUBMITTY_filename)
    os.system("sed -i -e \"s|CGI_USER='submitty_cgi'|HWCGI_USER='hwcgi'|g\" "+INSTALL_SUBMITTY_filename)
    os.system("sed -i -e \"s|DAEMONPHP_GROUP='submitty_daemonphp'|HWCRONPHP_GROUP='hwcronphp'|g\" "+INSTALL_SUBMITTY_filename)
    os.chmod(INSTALL_SUBMITTY_filename, 0o500)

    # repair & restart apache & phpfpm
    APACHE_FILENAME="/etc/apache2/sites-enabled/submitty.conf"
    os.system("sed -i -e \"s|submitty_cgi|hwcgi|g\" "+APACHE_FILENAME)
    APACHE_FILENAME2="/etc/apache2/sites-enabled/submitty_http.conf"
    os.system("sed -i -e \"s|submitty_cgi|hwcgi|g\" "+APACHE_FILENAME2)
    APACHE_FILENAME3="/etc/apache2/sites-enabled/vcs.conf"
    os.system("sed -i -e \"s|submitty_cgi|hwcgi|g\" "+APACHE_FILENAME3)
    PHPFPM_FILENAME="/etc/php/7.0/fpm/pool.d/submitty.conf"
    os.system("sed -i -e \"s|submitty_php|hwphp|g\" "+PHPFPM_FILENAME)
    os.system("systemctl start apache2.service")
    os.system("systemctl start php7.0-fpm.service")

    print ("finished rollback of migration changing system user names")

    pass
