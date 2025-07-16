#!/usr/bin/env bash
# shellcheck enable=all

#### This script repairs core services that are not currently active

declare -A services=(
    ["nginx"]="Nginx Server"
    ["apache2"]="Web Server"
    ["postgresql"]="Database Server"
    ["autograding"]="Autograding Shipper"
    ["submitty_websocket_server"]="WebSocket Server"
    ["submitty_daemon_jobs_handler"]="Daemon Jobs Handler"
)

log_service_restart() {
    local service="$1"
    local message="$2"
    local last_status="$3"

    local today
    today=$(date +%Y%m%d)
    local timestamp
    timestamp=$(date "+%Y-%m-%d:%H:%M:%S")
    local log_file="/var/local/submitty/logs/services/${today}.txt"

    if [[ ! -f "${log_file}" ]]; then
        sudo touch "${log_file}"
    fi

    echo -e "${timestamp}: ${services["${service}"]}\n" | sudo tee -a "${log_file}"
    echo -e "${message}\n\n${last_status}" | sudo tee -a "${log_file}"
    echo -e "----------------------------------------" | sudo tee -a "${log_file}"
}

repair_autograding() {
    local status_script="/usr/local/submitty/sbin/shipper_utils/systemctl_wrapper.py"
    local restart_script="/usr/local/submitty/sbin/restart_shipper_and_all_workers.py"

    cmd=(sudo python3 "${status_script}" --daemon "shipper" --target "primary" status)
    local status_output
    status_output=$("${cmd[@]}" 2>/dev/null)
    local status=$?

    if [[ "${status}" -ne 1 ]]; then
        local last_status
        last_status=$(sudo systemctl status "submitty_autograding_shipper")

        # Restart all autograding shippers and workers in the proper order
        restart_output=$(sudo python3 "${restart_script}" 2>&1 /dev/null)

        log_service_restart "autograding" \
            "Failure detected within the autograding shipper" \
            "${status_output}\n\n${restart_output}\n\n${last_status}"
    fi
}

repair_systemctl_service() {
    local service="$1"

    if ! sudo systemctl is-active --quiet "${service}"; then
        local last_status
        last_status=$(sudo systemctl status "${service}")
        log_service_restart "${service}" \
            "Failure detected within the ${services["${service}"]}" \
            "${last_status}"
        sudo systemctl restart "${service}"
    fi
}

repair_services() {
    for service in "${!services[@]}"; do
        case "${service}" in
            "autograding")
                repair_autograding
                ;;
            *)
                repair_systemctl_service "${service}"
                ;;
        esac
    done
}

repair_services
