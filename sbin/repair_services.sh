#!/usr/bin/env bash
# shellcheck enable=all

#### This script repairs core services that are not currently active

log_service_restart() {
    local service="$1"
    local action="$2"
    local last_status="$3"

    local today=$(date +%Y%m%d)
    local timestamp=$(date "+%Y-%m-%d %H:%M:%S")
    local log_file="/var/log/services/${today}.txt"

    if [[ ! -f "${log_file}" ]]; then
        sudo touch "${log_file}"
    fi

    echo -e "<${timestamp}>:<${service}>" | sudo tee -a "${log_file}" > /dev/null
    echo -e "${action}\n\n${last_status}" | sudo tee -a "${log_file}" > /dev/null
    echo -e "\n----------------------------------------\n" | sudo tee -a "${log_file}" > /dev/null
}

repair_autograding() {
    local restart=false
    local components=("shipper" "worker")
    local targets=("primary" "perform_on_all_workers")
    local status_codes=("inactive" "active" "failure" "bad_arguments" "io_error")
    local status_script="/usr/local/submitty/sbin/shipper_utils/systemctl_wrapper.py"
    local restart_script="/usr/local/submitty/sbin/restart_shipper_and_all_workers.py"

    for component in "${components[@]}"; do
        for target in "${targets[@]}"; do
            local output
            output=$(sudo python3 "$status_script" --daemon "$component" --target "$target" status)
            local status=$?

            if [[ "$status" -ne 1 ]]; then
                local last_status=$(sudo systemctl status "submitty_autograding_${component}")
                log_service_restart "autograding" \
                    "Failure detected in autograding ${component} for ${target} (status: ${status_codes[$status]})" \
                    "${output}\n\n${last_status}"
                restart=true
            fi
        done
    done

    if [[ "$restart" == true ]]; then
        sudo python3 "$restart_script"
    fi
}

repair_services() {
    # Simple restarts via systemctl
    local services=(
        "nginx"
        "apache2"
        "postgresql"
        "nullsmtpd"
        "submitty_websocket_server"
        "submitty_daemon_jobs_handler"
    )

    for service in "${services[@]}"; do
        if ! sudo systemctl is-active --quiet "${service}"; then
            local last_status=$(sudo systemctl status "${service}")
            log_service_restart "${service}" "Restarting ${service}" "${last_status}"
            sudo systemctl restart "${service}"
        fi
    done

    # Restart all autograding service components if needed via utility scripts
    repair_autograding
}

repair_services
