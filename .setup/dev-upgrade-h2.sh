#!/usr/bin/env bash
# shellcheck enable=all

set -e

display_help() {
    echo "Usage:"
    echo "$0 up|down [Options]"
    echo "Options:"
    echo "  -a '/etc/apache/s-e/submitty.conf' # Path to submitty config"
    echo "  -n '/etc/nginx/s-e/submitty.conf'  # Path to submitty config"
    echo "  -c '/etc/ssl/selfcert'             # Path to save certs     "
    echo "  -d 'localhost'                     # Domain                 "
    echo "  -f                                 # Overwrite certificate  "
    echo "Notes:"
    echo "  You have to use the same parameters for upgrading and downgrading"
}

# path to the apache configuration of submitty
P_APACHE="/etc/apache2/sites-enabled/submitty.conf"
# path to the nginx configuration of submitty
P_NGINX="/etc/nginx/sites-enabled/submitty.conf"

# generate certificate + key to the path below
P_CERT="/etc/ssl/selfcert"

# generate certificate + key to the domain below
S_DOMAIN="localhost"

# should force update the certificates
B_FORCE=0

# Submitty system-defined env vars and checks
SUBMITTY_INSTALL_DIR=${SUBMITTY_INSTALL_DIR:?}

# check configured domain
DOMAIN=$(jq -r ".submission_url" "${SUBMITTY_INSTALL_DIR}/config/submitty.json"          \
            | sed -e 's/[^/]*\/\/\([^@]*@\)\?\([^:/]*\).*/\2/' || :
        )


# log error to stderr and exit
panic() {
    echo -e >&2 "$0:\033[1;31m [ERR!] $1\033[0m"
    exit 1
}

# log warning to stderr
warn() {
    echo -e >&2 "$0:\033[1;33m [WARN] $1\033[0m"
}

# log info to stderr
info() {
    echo -e >&2 "$0:\033[0;36m [INFO] $1\033[0m"
}


# acquire a lock and open fd
LOCKFILE="/run/lock/$(basename "$0")"
exec {LOCKFD}>"${LOCKFILE}"

lock() {
    info "Acquiring a lock on ${LOCKFD}>${LOCKFILE}"
    flock -x -n "${LOCKFD}" || {
        panic "Another process is using the lockfile, exiting"
    }
    trap unlock EXIT
}

unlock() {
    info "Closing ${LOCKFD} and unlocking"
    exec {LOCKFD}>&-
    rm -f "${LOCKFILE}"
}


# generate cert and key to $P_CERT
generate_cert() {
    [[ ! -d "${P_CERT}" ]] && {
        warn "Could not find directory ${P_CERT}, creating"
        mkdir -p "${P_CERT}"
    }
    [[ -e "${P_CERT}/${S_DOMAIN}.crt" && -e "${P_CERT}/${S_DOMAIN}.key"
        && "${B_FORCE}" -eq "0" ]] && {
        info "Found existed certificates, use option -f to overwrite"
        openssl x509 -checkend 2592000 -noout -in "${P_CERT}/${S_DOMAIN}.crt" && return
        warn "The certificate will expire soon, regenerating..."
    }

    info "Generating certificates and keys"
    openssl req -x509                                                                    \
        -out "${P_CERT}/${S_DOMAIN}.crt" -keyout "${P_CERT}/${S_DOMAIN}.key"             \
        -newkey rsa:2048 -nodes -sha256 -subj "/CN=${S_DOMAIN}" -days 730                \
        -extensions EXT -config <(                                                       \
            printf "[dn]\nCN=%s\n[req]\ndistinguished_name = dn\n" "${S_DOMAIN}"
            printf "[EXT]\nsubjectAltName=DNS:%s\n" "${S_DOMAIN}"
            printf "keyUsage=digitalSignature,keyCertSign\nextendedKeyUsage=serverAuth\n"
            printf "basicConstraints=critical,CA:TRUE,pathlen:1")                        \
        || panic "Failed to generate certificate"
}

# update TLS configurations
update_config() {
    update_apache
    update_nginx
}
update_apache() {
    grep "SSL" "${P_APACHE}" && {
        warn "Found SSL configurations in apache, removing"
        remove_apache
    }

    info "Inserting TLS configurations to the apache config ${P_APACHE}"
    {
        sed -i --follow-symlinks "/^<VirtualHost /a SSLEngine\ on" "${P_APACHE}"
        sed -i --follow-symlinks "/^SSLE/a SSLCertificateFile\ \"${P_CERT}/${S_DOMAIN}.crt\"" "${P_APACHE}"
        sed -i --follow-symlinks "/^SSLC/a SSLCertificateKeyFile\ \"${P_CERT}/${S_DOMAIN}.key\"" "${P_APACHE}"
        sed -i --follow-symlinks "s/^SSL/\ \ \ \ SSL/g" "${P_APACHE}"
    } || panic "Failed to update the apache config"

    info "Double check that HTTP/2 module is enabled"
    phpver=$(php -r 'print PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
    #TODO: REMOVE THIS https://github.com/Submitty/Submitty/issues/11253
    # ERROR: Module php8.1 does not exist!
    set +e
    a2dismod "php${phpver}" mpm_prefork
    set -e
    a2enmod mpm_event http2

    info "Checking the integrity of Apache configuration"
    apachectl configtest || {
        cat "${P_APACHE}"
        remove_apache
        panic "Apache's configuration is invalid"
    }
}
update_nginx() {
    grep "ssl" "${P_NGINX}" && {
        warn "Found SSL configurations in nginx, removing"
        remove_nginx
    }

    info "Inserting TLS configurations to the nginx config ${P_NGINX}"
    {
        sed -i --follow-symlinks "s/default_server/ssl\ default_server/g" "${P_NGINX}"
        sed -i --follow-symlinks "/^\ *server_n/a ssl_certificate\ \"${P_CERT}/${S_DOMAIN}.crt\";" "${P_NGINX}"
        sed -i --follow-symlinks "/^ssl_c/a ssl_certificate_key\ \"${P_CERT}/${S_DOMAIN}.key\";" "${P_NGINX}"
        sed -i --follow-symlinks "s/^ssl_/\ \ \ \ ssl_/g" "${P_NGINX}"
    } || panic "Failed to update the nginx config"

    info "Checking the integrity of NginX configuration"
    nginx -t || {
        cat "${P_NGINX}"
        remove_nginx
        panic "NginX's configuration is invalid"
    }
}

# remove TLS configurations
remove_config() {
    remove_apache
    remove_nginx
}
remove_apache() {
    info "Removing TLS configurations from the apache config ${P_APACHE}"
    sed -i --follow-symlinks "/^\ *SSL/d" "${P_APACHE}" || warn "Failed to remove SSL from apache"
}
remove_nginx() {
    info "Removing TLS configurations from the nginx config ${P_NGINX}"
    {
        sed -i --follow-symlinks "/^\ *ssl_/d" "${P_NGINX}"
        sed -i --follow-symlinks "s/\ ssl//g" "${P_NGINX}"
    } || warn "Failed to remove SSL from nginx"
}


# deploy cert to system
deploy_syscert() {
    [[ -e "/usr/local/share/ca-certificates/${S_DOMAIN}.crt" ]] && {
        warn "Found existed symlink to the certificate, removing"
        remove_syscert
    }
    info "Deploying the certificate to the system"
    ln -s "${P_CERT}/${S_DOMAIN}.crt" "/usr/local/share/ca-certificates"                 \
        || panic "Failed to link the cert to /usr/local/share/ca-certificates"
}

# remove cert from system
remove_syscert() {
    info "Removing the certificate from the system"
    rm -v "/usr/local/share/ca-certificates/${S_DOMAIN}.crt"                             \
        || warn "Failed to unlink the certificate at /usr/local/share/ca-certificates"
}

# update the trust list in system
update_syscert() {
    info "Updating ca-certificates"
    update-ca-certificates || panic "Failed to update system certificate"
}


# reload web servers
reload_servers() {
    reload_apache
    reload_nginx
    reload_wsserver
}
reload_apache() {
    info "Reloading apache2"
    systemctl restart apache2 || {
        systemctl --no-pager status apache2
        panic "Failed to reload apache2"
    }
}
reload_nginx() {
    info "Reloading nginx"
    systemctl restart nginx || {
        systemctl --no-pager status nginx
        panic "Failed to reload nginx"
    }
}
reload_wsserver() {
    info "Reloading PHP WebSocket server"
    systemctl restart submitty_websocket_server || {
        systemctl --no-pager status submitty_websocket_server
        warn "Failed to reload PHP WebSocket server"
    }
}

# upgrade submitty configuration to HTTPS
upgrade_submitty() {
    info "Upgrading configurations for Submitty"
    sed -i --follow-symlinks "s/http\:\/\/${S_DOMAIN}/https\:\/\/${S_DOMAIN}/"           \
            "${SUBMITTY_INSTALL_DIR}/config/submitty.json"                               \
        || panic "Failed to upgrade to HTTPS for Submitty"
}

# downgrade submitty configuration to HTTP
downgrade_submitty() {
    info "Downgrading configurations for Submitty"
    sed -i --follow-symlinks "s/https\:\/\/${S_DOMAIN}/http\:\/\/${S_DOMAIN}/"           \
            "${SUBMITTY_INSTALL_DIR}/config/submitty.json"                               \
        || panic "Failed to downgrade to HTTP for Submitty"
}


upgrade() {
    generate_cert
    update_config
    deploy_syscert
    update_syscert
    reload_servers
    upgrade_submitty
}

downgrade() {
    remove_syscert
    update_syscert
    remove_config
    reload_servers
    downgrade_submitty
}


# Main Script ==========================================================

MODE="$1"
shift

[[ "${UID}" -ne 0 ]] && {
    panic "Please run as root"
}

while [[ "$#" -gt 0 ]]; do
    case "$1" in
        -a|--apache-config)
            P_APACHE="$2"
            shift 2
            ;;
        -n|--nginx-config)
            P_NGINX="$2"
            shift 2
            ;;
        -c|--cert-save)
            P_CERT="$2"
            shift 2
            ;;
        -d|--domain)
            S_DOMAIN="$2"
            shift 2
            ;;
        --i-know-what-i-am-doing-please-go-ahead)
            VAGRANT=1
            shift
            ;;
        -f|--force)
            B_FORCE=1
            shift
            ;;
        *)
            warn "Unknown option: $1"
            display_help
            exit 1
    esac
done

[[ "${HOSTNAME}" != "vagrant" && "${VAGRANT}" -ne 1 ]] && {
    warn "The script is designed for Submitty Vagrant VM in DEVELOPMENT environment"
    warn "If you believe this is an error, please append the following option:"
    panic "--i-know-what-i-am-doing-please-go-ahead"
}

[[ "${DOMAIN}" != "${S_DOMAIN}" ]] && {
    warn "The configured domain is different from Submitty's domain!"
    info "Configured: ${S_DOMAIN}"
    info "Submitty: ${DOMAIN}"
    warn "Press any key to continue"
    read -r
}

case "${MODE}" in
    "up"|"upgrade")
        lock
        info "Upgrading to h2+TLS"
        upgrade
        info "Now you need to use https://${S_DOMAIN}"
        ;;
    "down"|"downgrade")
        lock
        info "Downgrading to http1.1"
        downgrade
        info "Now you need to use http://${S_DOMAIN}"
        ;;
    *)
        warn "Unknown operation: ${MODE}"
        display_help
        exit 1
esac

info "Finished!"
