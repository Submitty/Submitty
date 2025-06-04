#!/usr/bin/env bash
# shellcheck enable=all

#### This script repairs core services that are not currently active

declare -A services=(
    ["nginx"]="Nginx Server"
    ["apache2"]="Web Server"
    ["postgresql"]="Database Server"
    ["nullsmtpd"]="Mail Server"
    ["autograding"]="Autograding Shipper/Workers"
    ["submitty_websocket_server"]="WebSocket Server"
    ["submitty_daemon_jobs_handler"]="Daemon Jobs Handler"
)

log_service_restart() {
    local service="$1"
    local message="$2"
    local last_status="$3"

    local today=$(date +%Y%m%d)
    local timestamp=$(date "+%Y-%m-%d:%H:%M:%S")
    local log_file="/var/log/services/${today}.txt"

    if [[ ! -f "${log_file}" ]]; then
        sudo touch "${log_file}"
    fi

    echo -e "${timestamp}: ${services[$service]}\n" | sudo tee -a "${log_file}" > /dev/null
    echo -e "${message}\n\n${last_status}" | sudo tee -a "${log_file}" > /dev/null
    echo -e "\n----------------------------------------\n" | sudo tee -a "${log_file}" > /dev/null
}

repair_autograding() {
    local restart=false
    local components=("shipper" "worker")
    local targets=("primary" "perform_on_all_workers")
    local status_script="/usr/local/submitty/sbin/shipper_utils/systemctl_wrapper.py"
    local restart_script="/usr/local/submitty/sbin/restart_shipper_and_all_workers.py"
    local workers=$(jq 'keys | length' /usr/local/submitty/config/autograding_workers.json)

    for component in "${components[@]}"; do
        for target in "${targets[@]}"; do
            local cmd_prefix

            # Ignore remote health checks if there are no remote workers configured
            if [[ "$target" == "perform_on_all_workers" && "$workers" -le 1 ]]; then
                continue
            fi

            if [[ "$target" == "primary" ]]; then
                cmd_prefix="sudo"
            else
                cmd_prefix="sudo -u submitty_daemon"
            fi

            local output=$($cmd_prefix python3 "$status_script" --daemon "$component" --target "$target" status)
            local status=$?

            if [[ "$status" -ne 1 ]] && [[ ! "$output" =~ "is active" ]]; then
                local source

                if [[ $target == "primary" ]]; then
                    source="local"
                else
                    source="remote"
                fi

                local spacing

                if [[ -z "$output" ]]; then
                    spacing=""
                else
                    spacing="\n\n"
                fi

                local last_status=$(sudo systemctl status "submitty_autograding_${component}")

                log_service_restart "autograding" \
                    "Failure detected within the autograding ${component} for ${source} machine(s)" \
                    "${output}${spacing}${last_status}"
                restart=true
            fi
        done
    done

    if [[ "$restart" == true ]]; then
        sudo python3 "$restart_script"
    fi
}

repair_systemctl_service() {
    local service="$1"

    if ! sudo systemctl is-active --quiet "${service}"; then
        local last_status=$(sudo systemctl status "${service}")
        log_service_restart "${service}" "Failure detected within the ${services[$service]}" "${last_status}"
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
