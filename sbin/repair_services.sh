#!/usr/bin/env bash
# shellcheck enable=all

#### This script repairs core services that are not currently running

repair_services() {
    services=(
        "nginx"
        "apache2"
        "postgresql"
        "nullsmtpd"
        "submitty_websocket_server"
        "submitty_daemon_jobs_handler"
    )

    for service in "${services[@]}"; do
        if ! sudo systemctl is-active --quiet "${service}"; then
            today=$(date +%Y%m%d)
            log_file="/var/log/services/${today}.txt"
            last_status=$(sudo systemctl status "${service}")

            if [[ ! -f "${log_file}" ]]; then
                sudo touch "${log_file}"
            fi

            echo -e "Restarting ${service}\n\n${last_status}" | sudo tee -a "${log_file}"
            echo -e "\n----------------------------------------\n" | sudo tee -a "${log_file}"

            sudo systemctl restart "${service}"
        fi
    done
}

repair_services