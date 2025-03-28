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
        "submitty_autograding_worker"
        "submitty_autograding_shipper"
        "submitty_daemon_jobs_handler"
    )

    for service in "${services[@]}"; do
        status=$(sudo systemctl status "${service}")
        if ! echo "${status}" | grep -q 'Active: active'; then
            echo "Restarting ${service}"
            sudo systemctl restart "${service}"
        fi
    done
}

repair_services