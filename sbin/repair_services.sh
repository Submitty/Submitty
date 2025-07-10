#!/usr/bin/env bash
# shellcheck enable=all

#### This script repairs core services that are not currently active

declare -A services=(
    ["nginx"]="Nginx Server"
    ["apache2"]="Web Server"
    ["postgresql"]="Database Server"
    ["autograding"]="Autograding Shipper/Workers"
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
    local restart=false
    local components=("shipper" "worker")
    local targets=("primary" "perform_on_all_workers")
    local status_script="/usr/local/submitty/sbin/shipper_utils/systemctl_wrapper.py"
    local restart_script="/usr/local/submitty/sbin/restart_shipper_and_all_workers.py"
    local workers
    workers=$(jq 'keys | length' /usr/local/submitty/config/autograding_workers.json)

    for component in "${components[@]}"; do
        for target in "${targets[@]}"; do
            # Ignore remote health checks if there are no remote workers configured
            if [[ "${target}" == "perform_on_all_workers" && "${workers}" -le 1 ]]; then
                continue
            fi

            local cmd=()

            if [[ "${target}" == "primary" ]]; then
                cmd=(sudo python3 "${status_script}" --daemon "${component}" --target "${target}" status)
            else
                cmd=(sudo -u submitty_daemon python3 "${status_script}" --daemon "${component}" --target "${target}" status)
            fi

            local output
            output=$("${cmd[@]}")
            local status=$?

            if [[ "${status}" -ne 1 ]]; then
                local source

                if [[ "${target}" == "primary" ]]; then
                    source="local"
                else
                    source="remote"
                fi

                local spacing

                # No need for a newline between the message and the status for empty output
                if [[ -z "${output}" ]]; then
                    spacing=""
                else
                    spacing="\n\n"
                fi

                local last_status
                last_status=$(sudo systemctl status "submitty_autograding_${component}")

                log_service_restart "autograding" \
                    "Failure detected within the autograding ${component} for ${source} machine(s)" \
                    "${output}${spacing}${last_status}"
                restart=true
            fi
        done
    done

    if [[ "${restart}" == true ]]; then
        sudo python3 "${restart_script}"
    fi
}

repair_systemctl_service() {
    local service="$1"

    if ! sudo systemctl is-active --quiet "${service}"; then
        local last_status
        last_status=$(sudo systemctl status "${service}")
        log_service_restart "${service}" "Failure detected within the ${services["${service}"]}" "${last_status}"
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
