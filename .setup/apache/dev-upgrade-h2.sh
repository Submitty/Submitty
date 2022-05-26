#!/usr/bin/env bash

display_help() {
    echo "Usage:"
    echo "$0 up|down [Options]"
    echo "Options:"
    echo "  -a '/etc/apac/s-e/submitty.conf'  # Path to submitty config"
    echo "  -c '/etc/ssl/selfcert'            # Path to save certs"
    echo "  -d 'localhost'                    # Domain"
    echo "Notes:"
    echo "  You have to use the same parameters for upgrading and downgrading"
}

# path to the configuration of submitty
P_APACHE="/etc/apache2/sites-enabled/submitty.conf"

# generate certificate + key to the path below
P_CERT="/etc/ssl/selfcert"

# generate certificate + key to the domain below
S_DOMAIN="localhost"

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


# generate cert and key to $P_CERT
generate_cert() {
    if [[ ! -d "${P_CERT}" ]]; then
        warn "Could not find directory ${P_CERT}, creating"
        mkdir -p "${P_CERT}"
    fi
    info "Generating certificates and keys"
    openssl req -x509                                                                    \
        -out "${P_CERT}/${S_DOMAIN}.crt"                                                 \
        -keyout "${P_CERT}/${S_DOMAIN}.key"                                              \
        -newkey rsa:2048 -nodes -sha256                                                  \
        -subj "/CN=${S_DOMAIN}"                                                          \
        -extensions EXT -config <( \
            printf "[dn]\nCN=%s\n[req]\ndistinguished_name = dn\n" "${S_DOMAIN}"
            printf "[EXT]\nsubjectAltName=DNS:%s\n" "${S_DOMAIN}"
            printf "keyUsage=digitalSignature\nextendedKeyUsage=serverAuth" )            \
        || panic "Failed to generate certificate"
}

# remove cert and key from $P_CERT
remove_cert() {
    info "Removing certificate and key"
    rm -v "${P_CERT}/${S_DOMAIN}.crt" || warn "Failed to unlink ${P_CERT}/${S_DOMAIN}.crt"
    rm -v "${P_CERT}/${S_DOMAIN}.key" || warn "Failed to unlink ${P_CERT}/${S_DOMAIN}.key"
}


# update apache TLS configuration
update_config() {
    if grep "SSL" "${P_APACHE}"; then
        warn "Found SSL configuration, removing"
        remove_config
    fi
    info "Inserting TLS configurations to the apache config ${P_APACHE}"
    sed -i "/^<VirtualHost /a SSLEngine\ on" "${P_APACHE}"                               \
        || panic "Failed to update the apache config"

    sed -i "/^SSLE/a SSLCertificateFile\ \"${P_CERT}/${S_DOMAIN}.crt\"" "${P_APACHE}"    \
        || panic "Failed to update the apache config"

    sed -i "/^SSLC/a SSLCertificateKeyFile\ \"${P_CERT}/${S_DOMAIN}.key\"" "${P_APACHE}" \
        || panic "Failed to update the apache config"

    sed -i "s/^SSL/\ \ \ \ SSL/" "${P_APACHE}"                                           \
        || panic "Failed to update the apache config"

    info "Double check that HTTP/2 module is enabled"
    a2dismod "php$(php -r 'print PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')" mpm_prefork
    a2enmod mpm_event http2

    info "Checking the integrity of Apache configuration"
    apachectl configtest || {
        cat "${P_APACHE}"
        panic "Apache's configuration is invalid"
    }
}

# remove apache TLS configuration
remove_config() {
    info "Removing TLS configurations from the apache config ${P_APACHE}"
    sed -i "/^\ \{4\}SSL/d" "${P_APACHE}" || warn "Failed to remove SSL from apache"
}


# deploy cert to system
deploy_syscert() {
    [ -e "/usr/local/share/ca-certificates/${S_DOMAIN}.crt" ] && {
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


# reload apache server
reload_apache() {
    info "Reloading apache2"
    systemctl restart apache2 || {
        systemctl --no-pager status apache2
        panic "Failed to reload apache2"
    }
}

# upgrade submitty configuration to HTTPS
upgrade_submitty() {
    info "Upgrading configurations for Submitty"
    sed -i "s/http\:\/\/${S_DOMAIN}/https\:\/\/${S_DOMAIN}/"                             \
            "${SUBMITTY_INSTALL_DIR}/config/submitty.json"                               \
        || panic "Failed to upgrade to HTTPS for Submitty"
}

# downgrade submitty configuration to HTTP
downgrade_submitty() {
    info "Downgrading configurations for Submitty"
    sed -i "s/https\:\/\/${S_DOMAIN}/http\:\/\/${S_DOMAIN}/"                             \
            "${SUBMITTY_INSTALL_DIR}/config/submitty.json"                               \
        || panic "Failed to downgrade to HTTP for Submitty"
}


upgrade() {
    generate_cert
    update_config
    deploy_syscert
    update_syscert
    reload_apache
    upgrade_submitty
}

downgrade() {
    remove_syscert
    update_syscert
    remove_config
    remove_cert
    reload_apache
    downgrade_submitty
}


# Main Script ==========================================================

MODE="$1"
shift

if [[ "$UID" -ne 0 ]]; then
    panic "Please run as root"
fi

while [[ "$#" -gt 0 ]]; do
    case "$1" in
        -a|--apache-config)
            P_APACHE="$2"
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
        *)
            warn "Unknown option: $1"
            display_help
            exit 1
    esac
done

if [[ "$HOSTNAME" != "vagrant" && "$VAGRANT" -ne 1 ]]; then
    warn "The script is designed for Submitty Vagrant VM in DEVELOPMENT environment"
    warn "If you believe this is an error, please append the following option:"
    panic "--i-know-what-i-am-doing-please-go-ahead"
fi

# check the domain settings
SUBMITTY_DOMAIN=$(jq ".submission_url" "${SUBMITTY_INSTALL_DIR:?}/config/submitty.json"  \
                    | cut -d'/' -f 3                                                     \
                    | cut -d':' -f 1)
if [[ "${SUBMITTY_DOMAIN}" != "${S_DOMAIN}" ]]; then
    warn "The configured domain is different from Submitty's domain!"
    info "Configured: ${S_DOMAIN}"
    info "Submitty: ${SUBMITTY_DOMAIN}"
    warn "Press any key to continue"
    read -r
fi

case "$MODE" in
    "up"|"upgrade")
        info "Upgrading to h2+TLS"
        upgrade
        info "Now you need to use https://${S_DOMAIN}"
        ;;
    "down"|"downgrade")
        info "Downgrading to http1.1"
        downgrade
        info "Now you need to use http://${S_DOMAIN}"
        ;;
    *)
        warn "Unknown operation: $MODE"
        display_help
        exit 1
esac

info "Finished!"
