#!/bin/bash

PHP_VERSION=$(php -r 'print PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')

SUBMITTY_CONFIG_DIR="/usr/local/submitty/config"
SUBMITTY_REPOSITORY=$(jq -r '.submitty_repository' "${SUBMITTY_CONFIG_DIR}/submitty.json")

# shellcheck disable=SC1091
source "${SUBMITTY_REPOSITORY}/.setup/install_submitty/get_globals.sh" "config=${SUBMITTY_CONFIG_DIR:?}"

# Edit php settings.  Note that if you need to accept larger files,
# you’ll need to increase both upload_max_filesize and
# post_max_filesize

sed -i -e 's/^max_execution_time = 30/max_execution_time = 60/g' "/etc/php/${PHP_VERSION}/fpm/php.ini"
sed -i -e 's/^upload_max_filesize = 2M/upload_max_filesize = 200M/g' "/etc/php/${PHP_VERSION}/fpm/php.ini"
sed -i -e 's/^session.gc_maxlifetime = 1440/session.gc_maxlifetime = 86400/' "/etc/php/${PHP_VERSION}/fpm/php.ini"
sed -i -e 's/^post_max_size = 8M/post_max_size = 200M/g' "/etc/php/${PHP_VERSION}/fpm/php.ini"
sed -i -e 's/^allow_url_fopen = On/allow_url_fopen = Off/g' "/etc/php/${PHP_VERSION}/fpm/php.ini"
sed -i -e 's/^session.cookie_httponly =/session.cookie_httponly = 1/g' "/etc/php/${PHP_VERSION}/fpm/php.ini"
# This should mimic the list of disabled functions that RPI uses on the HSS machine with the sole difference
# being that we do not disable phpinfo() on the vagrant machine as it's not a function that could be used for
# development of some feature, but it is useful for seeing information that could help debug something going wrong
# with our version of PHP.
DISABLED_FUNCTIONS="popen,pclose,proc_open,php_real_logo_guid,php_egg_logo_guid,php_ini_scanned_files,"
DISABLED_FUNCTIONS+="php_ini_loaded_file,readlink,symlink,link,set_file_buffer,proc_close,proc_terminate,"
DISABLED_FUNCTIONS+="proc_get_status,proc_nice,getmyuid,getmygid,getmyinode,putenv,get_current_user,"
DISABLED_FUNCTIONS+="magic_quotes_runtime,set_magic_quotes_runtime,import_request_variables,ini_alter,"
DISABLED_FUNCTIONS+="stream_socket_server,stream_socket_accept,stream_socket_pair,"
DISABLED_FUNCTIONS+="stream_get_transports,stream_wrapper_restore,mb_send_mail,openlog,syslog,closelog,pfsockopen,"
DISABLED_FUNCTIONS+="posix_kill,apache_child_terminate,apache_get_modules,apache_get_version,apache_lookup_uri,"
DISABLED_FUNCTIONS+="apache_reset_timeout,apache_response_headers,virtual,system,exec,shell_exec,passthru,"
DISABLED_FUNCTIONS+="pcntl_alarm,pcntl_fork,pcntl_waitpid,pcntl_wait,pcntl_wifexited,pcntl_wifstopped,"
DISABLED_FUNCTIONS+="pcntl_wifsignaled,pcntl_wexitstatus,pcntl_wtermsig,pcntl_wstopsig,pcntl_signal,"
DISABLED_FUNCTIONS+="pcntl_signal_dispatch,pcntl_get_last_error,pcntl_strerror,pcntl_sigprocmask,pcntl_sigwaitinfo,"
DISABLED_FUNCTIONS+="pcntl_sigtimedwait,pcntl_exec,pcntl_getpriority,pcntl_setpriority,"

if [ "${IS_VAGRANT}" != "1" ]; then
    DISABLED_FUNCTIONS+="phpinfo,"
fi

sed -i -e "s/^disable_functions = .*/disable_functions = ${DISABLED_FUNCTIONS}/g" "/etc/php/${PHP_VERSION}/fpm/php.ini"

if [ "${IS_VAGRANT}" == "1" ]; then
    # Create folder and give permissions to PHP user for xdebug profiling
    mkdir -p "${SUBMITTY_REPOSITORY}/.vagrant/Ubuntu/profiler"
    usermod -aG vagrant "${PHP_USER}"

    # Enable xdebug support for debugging
    phpenmod xdebug

    # In case you reprovision without wiping the drive, don't paste this twice
    if ! grep -q 'xdebug\.remote_enable' "/etc/php/${PHP_VERSION}/mods-available/xdebug.ini"
    then
        # Tell it to send requests to our host on port 9003 (PhpStorm default)
        cat << EOF >> "/etc/php/${PHP_VERSION}/mods-available/xdebug.ini"
xdebug.start_with_request=trigger
xdebug.client_port=9003
xdebug.discover_client_host=true
xdebug.mode=debug
EOF
    fi

    if ! grep 'xdebug\.profiler_enable_trigger' "/etc/php/${PHP_VERSION}/mods-available/xdebug.ini"
    then
        # Allow remote profiling and upload outputs to the shared folder
        cat << EOF >> "/etc/php/${PHP_VERSION}/mods-available/xdebug.ini"
xdebug.output_dir=${SUBMITTY_REPOSITORY}/.vagrant/Ubuntu/profiler
EOF
        sed -i -e "s/xdebug.mode=debug/xdebug.mode=debug,profile/g" "/etc/php/${PHP_VERSION}/mods-available/xdebug.ini"
    fi
fi
